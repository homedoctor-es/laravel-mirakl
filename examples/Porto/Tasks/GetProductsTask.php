<?php

namespace App\Containers\Mirakl\Tasks;

use App\Ship\Parents\Tasks\Task;
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;
use Mirakl\MMP\Shop\Request\Product\GetProductsRequest;
use Mirakl\MMP\Shop\Domain\Product\ProductReference;

/**
 * Example Task for Porto Pattern
 * Place this in: app/Containers/Mirakl/Tasks/GetProductsTask.php
 */
class GetProductsTask extends Task
{
    /**
     * Get products from Mirakl API
     *
     * @param array $productReferences Array of ['type' => 'EAN', 'reference' => '1234567890']
     * @param int $max
     * @param int $offset
     * @return \Mirakl\MMP\Shop\Domain\Product\ProductCollection
     */
    public function run(array $productReferences = [], int $max = 100, int $offset = 0)
    {
        $references = [];
        
        // Build product references
        foreach ($productReferences as $ref) {
            $references[] = new ProductReference($ref['type'], $ref['reference']);
        }
        
        $request = new GetProductsRequest($references);
        
        if (method_exists($request, 'setMax')) {
            $request->setMax($max);
        }
        
        if (method_exists($request, 'setOffset')) {
            $request->setOffset($offset);
        }
        
        return Mirakl::getProducts($request);
    }
}
