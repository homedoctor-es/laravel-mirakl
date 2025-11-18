<?php

namespace App\Containers\Mirakl\Actions;

use App\Containers\Mirakl\Tasks\GetOffersTask;
use App\Ship\Parents\Actions\Action;
use HomedoctorEs\Laravel\EventBridge\Facades\EventBridge;
use Illuminate\Support\Facades\Log;

/**
 * Example Action for Porto Pattern
 * Place this in: app/Containers/Mirakl/Actions/SyncOffersAction.php
 */
class SyncOffersAction extends Action
{
    /**
     * @param GetOffersTask $getOffersTask
     */
    public function __construct(
        private GetOffersTask $getOffersTask
    ) {}

    /**
     * Sync all offers from Mirakl to local database
     *
     * @param array $filters
     * @return array
     */
    public function run(array $filters = []): array
    {
        $allOffers = [];
        $offset = 0;
        $max = 100;
        $processedCount = 0;
        $errorCount = 0;
        
        // Emit start event
        EventBridge::publishGlobal('mirakl.sync.offers.started', [
            'filters' => $filters,
            'timestamp' => now()->toIso8601String(),
        ]);
        
        try {
            do {
                $offers = $this->getOffersTask->run($max, $offset, $filters);
                
                foreach ($offers as $offer) {
                    try {
                        // Process each offer
                        $this->processOffer($offer);
                        $processedCount++;
                        
                        // Emit progress event every 100 offers
                        if ($processedCount % 100 === 0) {
                            EventBridge::publishGlobal('mirakl.sync.offers.progress', [
                                'processed' => $processedCount,
                                'errors' => $errorCount,
                                'offset' => $offset,
                            ]);
                        }
                    } catch (\Exception $e) {
                        $errorCount++;
                        Log::error('Error processing offer', [
                            'offer_id' => $offer->getId(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                $allOffers = array_merge($allOffers, iterator_to_array($offers));
                $offset += $max;
                
            } while (count($offers) === $max);
            
            // Emit completion event
            EventBridge::publishGlobal('mirakl.sync.offers.completed', [
                'total_processed' => $processedCount,
                'total_errors' => $errorCount,
                'timestamp' => now()->toIso8601String(),
            ]);
            
        } catch (\Exception $e) {
            // Emit error event
            EventBridge::publishGlobal('mirakl.sync.offers.failed', [
                'error' => $e->getMessage(),
                'processed' => $processedCount,
                'timestamp' => now()->toIso8601String(),
            ]);
            
            throw $e;
        }
        
        return [
            'total' => $processedCount,
            'errors' => $errorCount,
            'offers' => $allOffers,
        ];
    }

    /**
     * Process individual offer
     *
     * @param \Mirakl\MMP\Shop\Domain\Offer\ShopOffer $offer
     * @return void
     */
    private function processOffer($offer): void
    {
        // Example: Store or update offer in local database
        // You would typically inject a repository or use a Task here
        
        // \App\Containers\Mirakl\Models\Offer::updateOrCreate(
        //     ['mirakl_id' => $offer->getId()],
        //     [
        //         'sku' => $offer->getProductSku(),
        //         'price' => $offer->getPrice(),
        //         'state' => $offer->getState(),
        //         'quantity' => $offer->getQuantity(),
        //         'updated_at' => $offer->getUpdateDate(),
        //         'raw_data' => json_encode($offer->toArray()),
        //     ]
        // );
    }
}
