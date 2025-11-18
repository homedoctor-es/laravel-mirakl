<?php

namespace HomedoctorEs\Laravel\Mirakl\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Mirakl\MMP\Shop\Domain\Offer\ShopOffer getOffer(\Mirakl\MMP\Shop\Request\Offer\GetOfferRequest $request)
 * @method static \Mirakl\MMP\Shop\Domain\Offer\OfferCollection getOffers(\Mirakl\MMP\Shop\Request\Offer\GetOffersRequest $request)
 * @method static \Mirakl\MMP\Shop\Domain\Product\ProductCollection getProducts(\Mirakl\MMP\Shop\Request\Product\GetProductsRequest $request)
 * @method static \Mirakl\MMP\Shop\Domain\Order\ShopOrderCollection getOrders(\Mirakl\MMP\Shop\Request\Order\GetOrdersRequest $request)
 * @method static \Psr\Http\Message\ResponseInterface run(\Mirakl\Core\Request\RequestInterface $request)
 * @method static \Mirakl\Core\Client\AbstractApiClient raw()
 *
 * @see \Mirakl\MMP\Shop\Client\ShopApiClient
 */
class Mirakl extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'mirakl';
    }
}
