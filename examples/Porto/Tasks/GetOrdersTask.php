<?php

namespace App\Containers\Mirakl\Tasks;

use App\Ship\Parents\Tasks\Task;
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;
use Mirakl\MMP\Shop\Request\Order\GetOrdersRequest;

/**
 * Example Task for Porto Pattern
 * Place this in: app/Containers/Mirakl/Tasks/GetOrdersTask.php
 */
class GetOrdersTask extends Task
{
    /**
     * Get orders from Mirakl API
     *
     * @param int $max
     * @param int $offset
     * @param \DateTime|null $startDate
     * @param \DateTime|null $endDate
     * @param array $orderStates Example: ['WAITING_ACCEPTANCE', 'SHIPPING']
     * @return \Mirakl\MMP\Shop\Domain\Order\ShopOrderCollection
     */
    public function run(
        int $max = 100,
        int $offset = 0,
        ?\DateTime $startDate = null,
        ?\DateTime $endDate = null,
        array $orderStates = []
    ) {
        $request = new GetOrdersRequest();
        $request->setMax($max);
        $request->setOffset($offset);
        
        if ($startDate) {
            $request->setStartDate($startDate);
        }
        
        if ($endDate) {
            $request->setEndDate($endDate);
        }
        
        if (!empty($orderStates)) {
            $request->setOrderStates($orderStates);
        }
        
        return Mirakl::getOrders($request);
    }
}
