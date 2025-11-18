<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mirakl API URL
    |--------------------------------------------------------------------------
    |
    | The base URL for your Mirakl API instance.
    | Example: https://your-instance.mirakl.net/api
    |
    */
    'api_url' => env('MIRAKL_API_URL'),

    /*
    |--------------------------------------------------------------------------
    | Mirakl API Key
    |--------------------------------------------------------------------------
    |
    | Your Mirakl API authentication key.
    |
    */
    'api_key' => env('MIRAKL_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Mirakl Shop ID
    |--------------------------------------------------------------------------
    |
    | Your Mirakl shop identifier. This is required when a user is associated
    | to multiple shops and should be specified to select the shop to be used
    | by the API.
    |
    */
    'shop_id' => env('MIRAKL_SHOP_ID'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout for API requests in seconds.
    |
    */
    'timeout' => env('MIRAKL_TIMEOUT', 30),
];
