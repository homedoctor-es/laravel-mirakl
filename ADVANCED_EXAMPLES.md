# Advanced Usage Examples

This document contains advanced usage examples for the Laravel Mirakl package.

## Table of Contents

1. [Bulk Import Products](#bulk-import-products)
2. [Order Management](#order-management)
3. [Inventory Synchronization](#inventory-synchronization)
4. [Rate Limit Handling](#rate-limit-handling)
5. [Custom Request Handlers](#custom-request-handlers)
6. [EventBridge Integration](#eventbridge-integration)

## Bulk Import Products

### Import Products to Mirakl

```php
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;
use Mirakl\MMP\Shop\Request\Product\ProductImportRequest;

class ImportProductsToMiraklTask extends Task
{
    public function run(string $csvFilePath)
    {
        $request = new ProductImportRequest();
        $request->setFile($csvFilePath);
        
        // Optional: Set import mode
        // $request->setImportMode('NORMAL'); // or 'REPLACE'
        
        $result = Mirakl::importProducts($request);
        
        return [
            'import_id' => $result->getImportId(),
            'tracking_url' => $result->getTrackingUrl(),
        ];
    }
}
```

### Monitor Import Status

```php
use Mirakl\MMP\Shop\Request\Product\ProductImportTrackingRequest;

class CheckImportStatusTask extends Task
{
    public function run(string $importId)
    {
        $request = new ProductImportTrackingRequest($importId);
        $result = Mirakl::getProductImportTracking($request);
        
        return [
            'status' => $result->getImportStatus(),
            'lines_read' => $result->getLinesRead(),
            'lines_in_error' => $result->getLinesInError(),
            'lines_in_success' => $result->getLinesInSuccess(),
        ];
    }
}
```

## Order Management

### Accept Orders Automatically

```php
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;
use Mirakl\MMP\Shop\Request\Order\AcceptOrderRequest;
use Mirakl\MMP\Shop\Domain\Order\AcceptOrderLine;

class AcceptOrderTask extends Task
{
    public function run(string $orderId, array $orderLines)
    {
        $acceptLines = [];
        
        foreach ($orderLines as $line) {
            $acceptLine = new AcceptOrderLine();
            $acceptLine->setAccepted(true);
            $acceptLine->setId($line['id']);
            $acceptLines[] = $acceptLine;
        }
        
        $request = new AcceptOrderRequest($orderId);
        $request->setOrderLines($acceptLines);
        
        $result = Mirakl::acceptOrder($request);
        
        return $result;
    }
}
```

### Ship Orders

```php
use Mirakl\MMP\Shop\Request\Order\ShipOrderRequest;
use Mirakl\MMP\Shop\Domain\Order\ShipmentTracking;

class ShipOrderTask extends Task
{
    public function run(
        string $orderId,
        string $carrier,
        string $trackingNumber,
        string $trackingUrl = null
    ) {
        $tracking = new ShipmentTracking();
        $tracking->setCarrierCode($carrier);
        $tracking->setTrackingNumber($trackingNumber);
        
        if ($trackingUrl) {
            $tracking->setTrackingUrl($trackingUrl);
        }
        
        $request = new ShipOrderRequest($orderId);
        $request->setShipmentTracking($tracking);
        
        return Mirakl::shipOrder($request);
    }
}
```

## Inventory Synchronization

### Sync Stock from Your System to Mirakl

```php
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;
use Mirakl\MMP\Shop\Request\Offer\UpdateOffersRequest;
use Mirakl\MMP\Shop\Domain\Offer\OfferUpdate;

class SyncStockToMiraklAction extends Action
{
    public function run(array $stockUpdates)
    {
        $offerUpdates = [];
        
        foreach ($stockUpdates as $update) {
            $offerUpdate = new OfferUpdate();
            $offerUpdate->setSku($update['sku']);
            $offerUpdate->setQuantity($update['quantity']);
            
            // Optional: Update price
            if (isset($update['price'])) {
                $offerUpdate->setPrice($update['price']);
            }
            
            $offerUpdates[] = $offerUpdate;
        }
        
        $request = new UpdateOffersRequest($offerUpdates);
        $result = Mirakl::updateOffers($request);
        
        return [
            'import_id' => $result->getImportId(),
            'lines' => count($offerUpdates),
        ];
    }
}
```

### Real-time Stock Sync with Queues

```php
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncProductStockJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;
    
    public function __construct(
        private string $sku,
        private int $quantity
    ) {}
    
    public function handle()
    {
        $offerUpdate = new OfferUpdate();
        $offerUpdate->setSku($this->sku);
        $offerUpdate->setQuantity($this->quantity);
        
        $request = new UpdateOffersRequest([$offerUpdate]);
        
        try {
            Mirakl::updateOffers($request);
        } catch (\Exception $e) {
            // Retry logic
            if ($this->attempts() < 3) {
                $this->release(60); // Retry after 60 seconds
            } else {
                Log::error('Failed to sync stock to Mirakl', [
                    'sku' => $this->sku,
                    'quantity' => $this->quantity,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
```

## Rate Limit Handling

### Custom Retry Logic with Exponential Backoff

```php
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;
use GuzzleHttp\Exception\ClientException;

class RateLimitAwareTask extends Task
{
    private const MAX_RETRIES = 5;
    private const INITIAL_BACKOFF = 1; // seconds
    
    public function run($request)
    {
        $attempt = 0;
        $backoff = self::INITIAL_BACKOFF;
        
        while ($attempt < self::MAX_RETRIES) {
            try {
                return Mirakl::run($request);
            } catch (ClientException $e) {
                if ($e->getResponse()->getStatusCode() !== 429) {
                    throw $e;
                }
                
                $attempt++;
                
                if ($attempt >= self::MAX_RETRIES) {
                    throw new \RuntimeException(
                        'Max retries exceeded for Mirakl API request',
                        0,
                        $e
                    );
                }
                
                // Exponential backoff
                $waitTime = $backoff * (2 ** $attempt);
                
                Log::warning('Rate limit hit, backing off', [
                    'attempt' => $attempt,
                    'wait_time' => $waitTime,
                ]);
                
                sleep($waitTime);
            }
        }
    }
}
```

### Distributed Rate Limiting with Redis

```php
use Illuminate\Support\Facades\Redis;

class DistributedRateLimitTask extends Task
{
    private const RATE_LIMIT_KEY = 'mirakl:rate_limit';
    private const MAX_REQUESTS_PER_HOUR = 1000;
    
    public function run($request)
    {
        $this->waitForRateLimit();
        
        try {
            $result = Mirakl::run($request);
            $this->incrementRateLimit();
            return $result;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 429) {
                $this->handleRateLimitHit();
            }
            throw $e;
        }
    }
    
    private function waitForRateLimit(): void
    {
        while ($this->isRateLimitExceeded()) {
            sleep(1);
        }
    }
    
    private function isRateLimitExceeded(): bool
    {
        $count = Redis::get(self::RATE_LIMIT_KEY) ?? 0;
        return $count >= self::MAX_REQUESTS_PER_HOUR;
    }
    
    private function incrementRateLimit(): void
    {
        $key = self::RATE_LIMIT_KEY;
        
        Redis::multi();
        Redis::incr($key);
        Redis::expire($key, 3600); // 1 hour
        Redis::exec();
    }
    
    private function handleRateLimitHit(): void
    {
        // Set rate limit counter to max
        Redis::setex(self::RATE_LIMIT_KEY, 3600, self::MAX_REQUESTS_PER_HOUR);
    }
}
```

## Custom Request Handlers

### Create Custom Request Builder

```php
class MiraklRequestBuilder
{
    public static function buildOfferUpdateRequest(
        array $products,
        array $priceStrategy = []
    ): UpdateOffersRequest {
        $updates = [];
        
        foreach ($products as $product) {
            $update = new OfferUpdate();
            $update->setSku($product['sku']);
            $update->setQuantity($product['stock']);
            
            // Apply price strategy
            if (!empty($priceStrategy)) {
                $price = self::calculatePrice(
                    $product['base_price'],
                    $priceStrategy
                );
                $update->setPrice($price);
            }
            
            $updates[] = $update;
        }
        
        return new UpdateOffersRequest($updates);
    }
    
    private static function calculatePrice(
        float $basePrice,
        array $strategy
    ): float {
        $margin = $strategy['margin'] ?? 0;
        $tax = $strategy['tax'] ?? 0;
        
        $price = $basePrice * (1 + $margin / 100);
        $price = $price * (1 + $tax / 100);
        
        return round($price, 2);
    }
}
```

## EventBridge Integration

### Subscribe to Sync Events

```php
// In EventServiceProvider or a dedicated listener

use HomedoctorEs\Laravel\EventBridge\Facades\EventBridge;

EventBridge::subscribe('mirakl.sync.offers.completed', function ($event) {
    Log::info('Mirakl sync completed', $event->data);
    
    // Send notification
    Notification::route('slack', config('slack.webhook'))
        ->notify(new MiraklSyncCompleted($event->data));
});

EventBridge::subscribe('mirakl.sync.offers.failed', function ($event) {
    Log::error('Mirakl sync failed', $event->data);
    
    // Alert administrators
    Admin::all()->each->notify(new MiraklSyncFailed($event->data));
});
```

### Custom Event Publisher

```php
class MiraklEventPublisher
{
    public static function publishOfferUpdate(
        string $sku,
        array $changes
    ): void {
        EventBridge::publishGlobal('mirakl.offer.updated', [
            'sku' => $sku,
            'changes' => $changes,
            'timestamp' => now()->toIso8601String(),
            'source' => 'mirakl_sync',
        ]);
    }
    
    public static function publishStockAlert(
        string $sku,
        int $currentStock,
        int $threshold
    ): void {
        EventBridge::publishGlobal('mirakl.stock.alert', [
            'sku' => $sku,
            'current_stock' => $currentStock,
            'threshold' => $threshold,
            'severity' => $currentStock === 0 ? 'critical' : 'warning',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
```

## Scheduled Synchronization

### Laravel Scheduler Integration

```php
// In app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // Sync offers every hour
    $schedule->command('mirakl:sync-offers')
        ->hourly()
        ->withoutOverlapping()
        ->runInBackground();
    
    // Sync orders every 15 minutes
    $schedule->command('mirakl:sync-orders')
        ->everyFifteenMinutes()
        ->withoutOverlapping();
    
    // Daily product sync at 2 AM
    $schedule->command('mirakl:sync-products')
        ->dailyAt('02:00')
        ->withoutOverlapping();
    
    // Weekly cleanup of old sync data
    $schedule->command('mirakl:cleanup')
        ->weekly()
        ->sundays()
        ->at('03:00');
}
```

## Testing

### Mock Mirakl Responses

```php
use Tests\TestCase;
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;
use Mirakl\MMP\Shop\Domain\Offer\OfferCollection;

class MiraklSyncTest extends TestCase
{
    public function test_sync_offers_successfully()
    {
        // Mock the Mirakl client
        $mockOffers = new OfferCollection();
        
        Mirakl::shouldReceive('getOffers')
            ->once()
            ->andReturn($mockOffers);
        
        $action = app(SyncOffersAction::class);
        $result = $action->run();
        
        $this->assertEquals(0, $result['total']);
    }
}
```

## Error Recovery

### Dead Letter Queue Handler

```php
class HandleFailedMiraklSyncJob implements ShouldQueue
{
    use Queueable;
    
    public function handle()
    {
        // Get failed jobs from dead letter queue
        $failedJobs = DB::table('failed_jobs')
            ->where('queue', 'mirakl')
            ->where('failed_at', '>', now()->subDay())
            ->get();
        
        foreach ($failedJobs as $job) {
            try {
                $payload = json_decode($job->payload, true);
                
                // Retry the job with modified parameters
                $this->retryJob($payload);
                
                // Remove from failed jobs
                DB::table('failed_jobs')->where('id', $job->id)->delete();
                
            } catch (\Exception $e) {
                Log::error('Failed to recover Mirakl job', [
                    'job_id' => $job->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
    
    private function retryJob(array $payload): void
    {
        // Extract and dispatch the original job
        // Implementation depends on your job structure
    }
}
```
