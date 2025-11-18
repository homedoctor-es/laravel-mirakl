# Package Structure

This document describes the structure and organization of the Laravel Mirakl package.

## Directory Structure

```
laravel-mirakl/
├── config/
│   └── mirakl.php                 # Configuration file
├── examples/
│   └── Porto/                     # Porto Pattern examples
│       ├── Actions/
│       │   └── SyncOffersAction.php
│       ├── Commands/
│       │   └── SyncOffersCommand.php
│       ├── Models/
│       │   └── Offer.php
│       └── Tasks/
│           ├── GetOffersTask.php
│           ├── GetOrdersTask.php
│           └── GetProductsTask.php
├── src/
│   ├── Facades/
│   │   └── Mirakl.php            # Facade for easy access
│   ├── Helpers/
│   │   └── MiraklHelper.php      # Helper utilities
│   └── MiraklServiceProvider.php  # Laravel service provider
├── .env.example                   # Environment variables example
├── .gitignore
├── composer.json                  # Package dependencies
├── LICENSE.md
├── README.md                      # Main documentation
└── STRUCTURE.md                   # This file
```

## Core Components

### 1. Service Provider (`MiraklServiceProvider.php`)

The service provider is responsible for:
- Registering the Mirakl client as a singleton in the Laravel container
- Publishing the configuration file
- Merging package configuration with app configuration

**Key Methods:**
- `register()`: Binds the Mirakl client to the container
- `boot()`: Publishes configuration files
- `provides()`: Declares provided services

### 2. Configuration (`config/mirakl.php`)

Contains all configurable options:
- `api_url`: Mirakl API base URL
- `api_key`: Authentication key
- `shop_id`: Shop identifier
- `timeout`: Request timeout

All values can be set via environment variables.

### 3. Facade (`Facades/Mirakl.php`)

Provides a convenient static interface to the Mirakl client:

```php
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;

$offers = Mirakl::getOffers($request);
```

### 4. Helper (`Helpers/MiraklHelper.php`)

Utility class with common operations:
- `fetchAllPaginated()`: Fetch all results with automatic pagination
- `executeWithRetry()`: Automatic retry on rate limits
- `getAllOffers()`: Quick method to get all offers
- `getAllProducts()`: Quick method to get all products
- `getAllOrders()`: Quick method to get all orders

## Porto Pattern Integration

The package includes examples for integrating with Porto architecture:

### Tasks

Tasks are small, focused operations that interact directly with the Mirakl API:

- `GetOffersTask`: Fetch offers with filters
- `GetProductsTask`: Fetch products
- `GetOrdersTask`: Fetch orders with date filters

### Actions

Actions orchestrate multiple tasks and contain business logic:

- `SyncOffersAction`: Complete synchronization of offers
  - Handles pagination
  - Processes each offer
  - Emits EventBridge events for progress tracking
  - Error handling and logging

### Commands

Artisan commands for CLI operations:

- `SyncOffersCommand`: CLI wrapper for SyncOffersAction
  - Supports filtering options
  - Progress display
  - Error reporting

### Models

Example Eloquent models for storing Mirakl data:

- `Offer`: Represents a Mirakl offer
  - Scopes for common queries
  - Helper method `createFromMirakl()`

## Usage Patterns

### 1. Simple Usage (Facade)

```php
use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;

$request = new GetOffersRequest();
$offers = Mirakl::getOffers($request);
```

### 2. Dependency Injection

```php
public function __construct(
    private ShopApiClient $mirakl
) {}

public function handle() {
    $offers = $this->mirakl->getOffers($request);
}
```

### 3. Helper Methods

```php
use HomedoctorEs\Laravel\Mirakl\Helpers\MiraklHelper;

// Get all offers with automatic pagination
$allOffers = MiraklHelper::getAllOffers();

// Execute with automatic retry on rate limits
$result = MiraklHelper::executeWithRetry($request);
```

### 4. Porto Pattern (Recommended for complex applications)

```php
// In a controller or job
public function __construct(
    private SyncOffersAction $syncOffersAction
) {}

public function sync() {
    $result = $this->syncOffersAction->run([
        'state' => '1100',
        'updated_since' => now()->subDays(7),
    ]);
}
```

## EventBridge Integration

The package examples show how to integrate with `homedoctor-es/laravel-eventbridge-pubsub`:

Events emitted during synchronization:
- `mirakl.sync.offers.started`
- `mirakl.sync.offers.progress`
- `mirakl.sync.offers.completed`
- `mirakl.sync.offers.failed`

## Error Handling

The package handles common Mirakl API errors:

1. **Rate Limiting (429)**
   - Automatically retries after waiting the specified time
   - Configurable max retries

2. **Not Found (404)**
   - Throws `ClientException` for proper handling

3. **Validation Errors (400)**
   - Throws `ClientException` with response details

## Best Practices

1. **Use Tasks for API calls**: Keep API interactions in Tasks
2. **Use Actions for business logic**: Orchestrate Tasks in Actions
3. **Use Commands for scheduling**: Wrap Actions in Commands
4. **Store raw data**: Always save the raw API response for debugging
5. **Implement pagination**: Use helpers for large datasets
6. **Handle rate limits**: Use `executeWithRetry()` or implement retry logic
7. **Log errors**: Always log exceptions with context
8. **Emit events**: Use EventBridge for progress tracking

## Testing

When testing, you can mock the Mirakl client:

```php
$mockClient = Mockery::mock(ShopApiClient::class);
$this->app->instance('mirakl', $mockClient);

$mockClient->shouldReceive('getOffers')
    ->once()
    ->andReturn($expectedOffers);
```

## Extension Points

The package is designed to be extended:

1. **Custom Helpers**: Add domain-specific helpers in `Helpers/`
2. **Custom Tasks**: Create new Tasks for specific API endpoints
3. **Custom Actions**: Build complex workflows with multiple Tasks
4. **Event Listeners**: Subscribe to EventBridge events for custom processing

## Dependencies

- `mirakl/sdk-php-shop`: Official Mirakl PHP SDK
- `illuminate/support`: Laravel framework support
- `homedoctor-es/laravel-eventbridge-pubsub`: (Optional) For event publishing

## Version Compatibility

- PHP: ^8.1, ^8.2, ^8.3
- Laravel: ^10.0, ^11.0
- Mirakl SDK: ^1.0

## Contributing

When contributing to this package:

1. Follow PSR-12 coding standards
2. Add tests for new features
3. Update documentation
4. Use semantic versioning
5. Create Porto-compatible examples

## Support

For issues or questions:
1. Check the README.md
2. Review examples in `examples/Porto/`
3. Consult Mirakl API documentation
4. Open an issue on GitHub
