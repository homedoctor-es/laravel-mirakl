<?php

namespace App\Containers\Mirakl\Tasks;

use App\Ship\Parents\Tasks\Task;
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;
use Mirakl\MMP\Shop\Request\Offer\GetOffersRequest;

/**
 * Example Task for Porto Pattern
 * Place this in: app/Containers/Mirakl/Tasks/GetOffersTask.php
 */
class GetOffersTask extends Task
{
    /**
     * Get offers from Mirakl API
     *
     * @param int $max
     * @param int $offset
     * @param array $filters
     * @return \Mirakl\MMP\Shop\Domain\Offer\OfferCollection
     */
    public function run(int $max = 100, int $offset = 0, array $filters = [])
    {
        $request = new GetOffersRequest();
        $request->setMax($max);
        $request->setOffset($offset);
        
        // Apply optional filters
        if (isset($filters['sku'])) {
            $request->setSku($filters['sku']);
        }
        
        if (isset($filters['state'])) {
            $request->setState($filters['state']);
        }
        
        if (isset($filters['updated_since'])) {
            $request->setUpdatedSince($filters['updated_since']);
        }
        
        return Mirakl::getOffers($request);
    }
}
