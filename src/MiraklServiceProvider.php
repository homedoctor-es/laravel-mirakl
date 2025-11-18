<?php

namespace HomedoctorEs\Laravel\Mirakl;

use Illuminate\Support\ServiceProvider;
use Mirakl\MMP\Shop\Client\ShopApiClient;

class MiraklServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/mirakl.php',
            'mirakl'
        );

        $this->app->singleton('mirakl', function ($app) {
            $config = $app['config']['mirakl'];

            if (empty($config['api_url']) || empty($config['api_key']) || empty($config['shop_id'])) {
                throw new \InvalidArgumentException(
                    'Mirakl API configuration is incomplete. Please set MIRAKL_API_URL, MIRAKL_API_KEY and MIRAKL_SHOP_ID in your .env file.'
                );
            }

            return new ShopApiClient(
                $config['api_url'],
                $config['api_key'],
                $config['shop_id']
            );
        });
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/mirakl.php' => config_path('mirakl.php'),
        ], 'config');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['mirakl'];
    }
}
