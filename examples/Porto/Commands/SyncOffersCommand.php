<?php

namespace App\Containers\Mirakl\UI\CLI\Commands;

use App\Containers\Mirakl\Actions\SyncOffersAction;
use App\Ship\Parents\Commands\ConsoleCommand;
use Illuminate\Support\Facades\Log;

/**
 * Example Artisan Command for Porto Pattern
 * Place this in: app/Containers/Mirakl/UI/CLI/Commands/SyncOffersCommand.php
 *
 * Usage:
 * php artisan mirakl:sync-offers
 * php artisan mirakl:sync-offers --sku=ABC123
 * php artisan mirakl:sync-offers --state=1100
 * php artisan mirakl:sync-offers --since="2024-01-01"
 */
class SyncOffersCommand extends ConsoleCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mirakl:sync-offers
                            {--sku= : Filter by SKU}
                            {--state= : Filter by state code (e.g., 1100 for active)}
                            {--since= : Filter by updated since date (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync offers from Mirakl to local database';

    /**
     * Execute the console command.
     */
    public function handle(SyncOffersAction $syncOffersAction): int
    {
        $this->info('Starting Mirakl offers synchronization...');
        
        $filters = [];
        
        // Build filters from options
        if ($sku = $this->option('sku')) {
            $filters['sku'] = $sku;
            $this->info("Filtering by SKU: {$sku}");
        }
        
        if ($state = $this->option('state')) {
            $filters['state'] = $state;
            $this->info("Filtering by state: {$state}");
        }
        
        if ($since = $this->option('since')) {
            try {
                $filters['updated_since'] = new \DateTime($since);
                $this->info("Filtering by updated since: {$since}");
            } catch (\Exception $e) {
                $this->error("Invalid date format: {$since}");
                return self::FAILURE;
            }
        }
        
        try {
            $result = $syncOffersAction->run($filters);
            
            $this->newLine();
            $this->info("✓ Synchronization completed successfully!");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Processed', $result['total']],
                    ['Errors', $result['errors']],
                    ['Success Rate', round(($result['total'] - $result['errors']) / max($result['total'], 1) * 100, 2) . '%'],
                ]
            );
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('✗ Synchronization failed: ' . $e->getMessage());
            Log::error('Mirakl sync command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return self::FAILURE;
        }
    }
}
