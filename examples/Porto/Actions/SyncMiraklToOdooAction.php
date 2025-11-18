<?php

namespace App\Containers\Mirakl\Actions;

use App\Containers\Mirakl\Tasks\GetOffersTask;
use App\Containers\Odoo\Tasks\CreateProductTask as OdooCreateProductTask;
use App\Containers\Odoo\Tasks\UpdateProductStockTask as OdooUpdateProductStockTask;
use App\Ship\Parents\Actions\Action;
use HomedoctorEs\Laravel\EventBridge\Facades\EventBridge;
use Illuminate\Support\Facades\Log;

/**
 * Example Action for syncing Mirakl offers to Odoo products
 * Place this in: app/Containers/Mirakl/Actions/SyncMiraklToOdooAction.php
 *
 * This action demonstrates how to:
 * 1. Fetch offers from Mirakl
 * 2. Transform Mirakl data to Odoo format
 * 3. Create/Update products in Odoo
 * 4. Sync stock levels
 */
class SyncMiraklToOdooAction extends Action
{
    public function __construct(
        private GetOffersTask $getMiraklOffersTask,
        private OdooCreateProductTask $odooCreateProductTask,
        private OdooUpdateProductStockTask $odooUpdateStockTask,
    ) {}

    /**
     * Sync Mirakl offers to Odoo products
     *
     * @param array $filters
     * @return array
     */
    public function run(array $filters = []): array
    {
        $stats = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
        ];

        EventBridge::publishGlobal('mirakl.odoo.sync.started', [
            'filters' => $filters,
            'timestamp' => now()->toIso8601String(),
        ]);

        try {
            $offset = 0;
            $max = 100;

            do {
                // Get offers from Mirakl
                $offers = $this->getMiraklOffersTask->run($max, $offset, $filters);

                foreach ($offers as $offer) {
                    try {
                        $this->syncOfferToOdoo($offer, $stats);
                        $stats['processed']++;

                        // Emit progress every 50 items
                        if ($stats['processed'] % 50 === 0) {
                            EventBridge::publishGlobal('mirakl.odoo.sync.progress', [
                                'processed' => $stats['processed'],
                                'created' => $stats['created'],
                                'updated' => $stats['updated'],
                                'errors' => $stats['errors'],
                            ]);
                        }
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        Log::error('Error syncing Mirakl offer to Odoo', [
                            'offer_id' => $offer->getId(),
                            'sku' => $offer->getProductSku(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $offset += $max;

            } while (count($offers) === $max);

            EventBridge::publishGlobal('mirakl.odoo.sync.completed', [
                'stats' => $stats,
                'timestamp' => now()->toIso8601String(),
            ]);

        } catch (\Exception $e) {
            EventBridge::publishGlobal('mirakl.odoo.sync.failed', [
                'error' => $e->getMessage(),
                'stats' => $stats,
                'timestamp' => now()->toIso8601String(),
            ]);

            throw $e;
        }

        return $stats;
    }

    /**
     * Sync individual offer to Odoo
     *
     * @param \Mirakl\MMP\Shop\Domain\Offer\ShopOffer $offer
     * @param array &$stats
     * @return void
     */
    private function syncOfferToOdoo($offer, array &$stats): void
    {
        // Transform Mirakl offer data to Odoo product format
        $productData = $this->transformOfferToProductData($offer);

        // Check if product already exists in Odoo by SKU (default_code)
        $existingProduct = $this->findOdooProductBySku($offer->getProductSku());

        if ($existingProduct) {
            // Update existing product
            $this->updateOdooProduct($existingProduct['id'], $productData);
            $stats['updated']++;
        } else {
            // Create new product
            $this->odooCreateProductTask->run($productData);
            $stats['created']++;
        }

        // Sync stock levels
        $this->syncStockLevels($offer);
    }

    /**
     * Transform Mirakl offer to Odoo product data
     *
     * @param \Mirakl\MMP\Shop\Domain\Offer\ShopOffer $offer
     * @return array
     */
    private function transformOfferToProductData($offer): array
    {
        return [
            'name' => $offer->getProductTitle() ?? $offer->getProductSku(),
            'default_code' => $offer->getProductSku(), // SKU in Odoo
            'list_price' => $offer->getPrice(), // Sale price
            'standard_price' => $offer->getOriginPrice() ?? $offer->getPrice(), // Cost price
            'type' => 'product', // Stockable product
            'active' => $offer->getActive(),
            'description_sale' => $offer->getDescription(),
            
            // Mirakl specific fields (you might need to create these custom fields in Odoo)
            'x_mirakl_offer_id' => $offer->getId(),
            'x_mirakl_state' => $offer->getState(),
            'x_mirakl_updated_at' => $offer->getUpdateDate()?->format('Y-m-d H:i:s'),
            
            // Additional fields based on your Odoo setup
            // 'categ_id' => $this->getCategoryId($offer),
            // 'uom_id' => 1, // Unit of measure
            // 'uom_po_id' => 1, // Purchase unit of measure
        ];
    }

    /**
     * Find Odoo product by SKU
     *
     * @param string $sku
     * @return array|null
     */
    private function findOdooProductBySku(string $sku): ?array
    {
        // This would use your Odoo integration
        // Example using the Odoo model pattern:
        //
        // use App\Containers\Odoo\Models\Product;
        //
        // $products = Product::search([
        //     ['default_code', '=', $sku]
        // ], 1);
        //
        // return $products->first() ?? null;

        return null;
    }

    /**
     * Update Odoo product
     *
     * @param int $productId
     * @param array $data
     * @return void
     */
    private function updateOdooProduct(int $productId, array $data): void
    {
        // This would use your Odoo integration
        // Example:
        //
        // use App\Containers\Odoo\Models\Product;
        //
        // $product = new Product();
        // $product->setOdooId($productId);
        // $product->update($data);
    }

    /**
     * Sync stock levels from Mirakl to Odoo
     *
     * @param \Mirakl\MMP\Shop\Domain\Offer\ShopOffer $offer
     * @return void
     */
    private function syncStockLevels($offer): void
    {
        // Get the quantity from Mirakl
        $quantity = $offer->getQuantity();

        // Update stock in Odoo
        $this->odooUpdateStockTask->run(
            sku: $offer->getProductSku(),
            quantity: $quantity,
            location: config('odoo.default_stock_location', 'Stock') // Your warehouse location
        );
    }

    /**
     * Map Mirakl category to Odoo category
     * (You would need to maintain a mapping table)
     *
     * @param \Mirakl\MMP\Shop\Domain\Offer\ShopOffer $offer
     * @return int|null
     */
    private function getCategoryId($offer): ?int
    {
        // Example mapping logic
        // You might want to store this in a database table:
        // mirakl_odoo_category_mappings (mirakl_category_id, odoo_category_id)
        
        return null; // Default category or mapping result
    }
}
