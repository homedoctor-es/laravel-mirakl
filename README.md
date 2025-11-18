# Laravel Mirakl

Laravel integration for the [Mirakl PHP SDK](https://github.com/mirakl/sdk-php-shop) including a shop client wrapper.

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

With Composer installed, you can then install the extension using the following commands:

```bash
$ php composer.phar require homedoctor-es/laravel-mirakl
```

or add

```json
"require": {
    "homedoctor-es/laravel-mirakl": "*"
}
```

to the require section of your `composer.json` file.

## Configuration

### 1. Register the ServiceProvider (Laravel < 11)

> **Note:** For Laravel 11+, the service provider is auto-discovered.

Register the ServiceProvider in your `config/app.php` service provider list:

```php
// config/app.php
return [
    //other stuff
    'providers' => [
        //other stuff
        \HomedoctorEs\Laravel\Mirakl\MiraklServiceProvider::class,
    ];
];
```

### 2. Add the Facade (Optional)

If you want, you can add the following facade to the `$aliases` section:

```php
// config/app.php
return [
    //other stuff
    'aliases' => [
        //other stuff
        'Mirakl' => \HomedoctorEs\Laravel\Mirakl\Facades\Mirakl::class,
    ];
];
```

### 3. Publish the Configuration

Publish the package configuration file:

```bash
$ php artisan vendor:publish --provider='HomedoctorEs\Laravel\Mirakl\MiraklServiceProvider'
```

### 4. Set Environment Variables

Set the API credentials in your `.env` file:

```env
MIRAKL_API_URL=https://your-instance.mirakl.net/api
MIRAKL_API_KEY=your_api_key_here
MIRAKL_SHOP_ID=your_shop_id_here
MIRAKL_TIMEOUT=30
```

Or you can set them directly in the `config/mirakl.php` file:

```php
// config/mirakl.php
return [
    'api_url' => 'https://your-instance.mirakl.net/api',
    'api_key' => 'your_api_key_here',
    'shop_id' => 'your_shop_id_here',
    'timeout' => 30,
];
```

## Usage

You can use the facade alias `Mirakl` to execute API calls. The authentication params will be automatically injected.

### Using the Facade

```php
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;
use Mirakl\MMP\Shop\Request\Offer\GetOfferRequest;
use Mirakl\MMP\Shop\Request\Offer\GetOffersRequest;

// Get a single offer
$request = new GetOfferRequest('OFFER_ID');
$offer = Mirakl::getOffer($request);

// Get all offers with pagination
$request = new GetOffersRequest();
$request->setMax(100); // Max 100 per page
$request->setOffset(0);
$offers = Mirakl::getOffers($request);

// You can also get raw response
$rawResponse = Mirakl::run($request);
// or
$rawResponse = Mirakl::raw()->getOffer($request);
```

### Using Dependency Injection

```php
use Mirakl\MMP\Shop\Client\ShopApiClient;
use Mirakl\MMP\Shop\Request\Product\GetProductsRequest;

class ProductController extends Controller
{
    protected $mirakl;

    public function __construct(ShopApiClient $mirakl)
    {
        $this->mirakl = $mirakl;
    }

    public function index()
    {
        $request = new GetProductsRequest();
        $products = $this->mirakl->getProducts($request);
        
        return response()->json($products);
    }
}
```

### Working with Orders

```php
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;
use Mirakl\MMP\Shop\Request\Order\GetOrdersRequest;

$request = new GetOrdersRequest();
$request->setMax(50);
$request->setOffset(0);

// You can also filter by date
$request->setStartDate(new \DateTime('-30 days'));
$request->setEndDate(new \DateTime());

$orders = Mirakl::getOrders($request);

foreach ($orders as $order) {
    echo $order->getId() . ' - ' . $order->getStatus() . PHP_EOL;
}
```

### Pagination Example

```php
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;
use Mirakl\MMP\Shop\Request\Offer\GetOffersRequest;

function getAllOffers()
{
    $allOffers = [];
    $offset = 0;
    $max = 100;
    
    do {
        $request = new GetOffersRequest();
        $request->setMax($max);
        $request->setOffset($offset);
        
        $result = Mirakl::run($request);
        $data = json_decode($result->getBody()->getContents(), true);
        
        $offers = $data['offers'] ?? [];
        $totalCount = $data['total_count'] ?? 0;
        
        $allOffers = array_merge($allOffers, $offers);
        $offset += $max;
        
    } while (count($allOffers) < $totalCount);
    
    return $allOffers;
}
```

### Error Handling

```php
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;
use Mirakl\MMP\Shop\Request\Offer\GetOfferRequest;
use GuzzleHttp\Exception\ClientException;

try {
    $request = new GetOfferRequest('INVALID_OFFER_ID');
    $offer = Mirakl::getOffer($request);
} catch (ClientException $e) {
    // Handle API errors (404, 400, etc.)
    $statusCode = $e->getResponse()->getStatusCode();
    $errorBody = $e->getResponse()->getBody()->getContents();
    
    Log::error('Mirakl API Error', [
        'status_code' => $statusCode,
        'error' => $errorBody
    ]);
} catch (\Exception $e) {
    // Handle other errors
    Log::error('Unexpected error', ['error' => $e->getMessage()]);
}
```

### Rate Limiting

Mirakl API has rate limits. If you receive an HTTP 429 "Too Many Requests" error, you should wait before making a new request. The response will contain a `Retry-After` header:

```php
use GuzzleHttp\Exception\ClientException;

try {
    $result = Mirakl::getOffers($request);
} catch (ClientException $e) {
    if ($e->getResponse()->getStatusCode() === 429) {
        $retryAfter = $e->getResponse()->getHeader('Retry-After')[0] ?? 60;
        sleep((int) $retryAfter);
        // Retry the request
    }
}
```

## Integration with Porto Pattern

If you're using this package in a Porto-based architecture (like Apiato), here's how you might structure it:

### Task Example

```php
namespace App\Containers\Mirakl\Tasks;

use App\Ship\Parents\Tasks\Task;
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;
use Mirakl\MMP\Shop\Request\Offer\GetOffersRequest;

class GetOffersTask extends Task
{
    public function run(int $max = 100, int $offset = 0)
    {
        $request = new GetOffersRequest();
        $request->setMax($max);
        $request->setOffset($offset);
        
        return Mirakl::getOffers($request);
    }
}
```

### Action Example

```php
namespace App\Containers\Mirakl\Actions;

use App\Containers\Mirakl\Tasks\GetOffersTask;
use App\Ship\Parents\Actions\Action;

class SyncOffersAction extends Action
{
    public function __construct(
        private GetOffersTask $getOffersTask
    ) {}

    public function run()
    {
        $allOffers = [];
        $offset = 0;
        $max = 100;
        
        do {
            $offers = $this->getOffersTask->run($max, $offset);
            
            // Process offers...
            foreach ($offers as $offer) {
                // Store in database, etc.
            }
            
            $offset += $max;
            
        } while (count($offers) === $max);
        
        return $allOffers;
    }
}
```

## Available Request Types

The Mirakl SDK provides numerous request types. Here are some commonly used ones:

### Offers
- `GetOfferRequest` - Get a single offer
- `GetOffersRequest` - Get all offers
- `CreateOfferRequest` - Create new offers
- `UpdateOfferRequest` - Update existing offers

### Products
- `GetProductsRequest` - Get products
- `ImportProductsRequest` - Import products
- `SynchronizeProductsRequest` - Synchronize products

### Orders
- `GetOrdersRequest` - Get orders
- `AcceptOrderRequest` - Accept an order
- `RefundOrderRequest` - Refund an order

### Shipping
- `GetShippingRatesRequest` - Get shipping rates
- `UpdateShippingRequest` - Update shipping information

For a complete list of available requests, please refer to the [Mirakl SDK documentation](https://github.com/mirakl/sdk-php-shop).

## Testing

```bash
$ composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Homedoctor](https://github.com/homedoctor-es)
- [All Contributors](https://github.com/homedoctor-es/laravel-mirakl/contributors)
