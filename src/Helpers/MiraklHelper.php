<?php

namespace HomedoctorEs\Laravel\Mirakl\Helpers;

use HomedoctorEs\Laravel\Mirakl\Facades\Mirakl;
use Mirakl\Core\Request\RequestInterface;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;

class MiraklHelper
{
    /**
     * Execute a paginated request and return all results
     *
     * @param RequestInterface $request
     * @param int $maxPerPage
     * @param callable|null $callback Optional callback to process each page
     * @return array
     */
    public static function fetchAllPaginated(
        RequestInterface $request,
        int $maxPerPage = 100,
        ?callable $callback = null
    ): array {
        $allResults = [];
        $offset = 0;
        
        do {
            if (method_exists($request, 'setMax')) {
                $request->setMax($maxPerPage);
            }
            
            if (method_exists($request, 'setOffset')) {
                $request->setOffset($offset);
            }
            
            $result = Mirakl::run($request);
            $data = json_decode($result->getBody()->getContents(), true);
            
            // Try different possible response structures
            $items = $data['offers'] ?? $data['products'] ?? $data['orders'] ?? $data['data'] ?? [];
            $totalCount = $data['total_count'] ?? count($items);
            
            if ($callback) {
                $callback($items, $offset);
            }
            
            $allResults = array_merge($allResults, $items);
            $offset += $maxPerPage;
            
            // Avoid infinite loop
            if (empty($items)) {
                break;
            }
            
        } while (count($allResults) < $totalCount);
        
        return $allResults;
    }

    /**
     * Execute a request with automatic retry on rate limit
     *
     * @param RequestInterface $request
     * @param int $maxRetries
     * @return mixed
     */
    public static function executeWithRetry(RequestInterface $request, int $maxRetries = 3)
    {
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                return Mirakl::run($request);
            } catch (ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();
                
                if ($statusCode === 429) {
                    $retryAfter = $e->getResponse()->getHeader('Retry-After')[0] ?? 60;
                    
                    Log::warning('Mirakl rate limit hit, waiting ' . $retryAfter . ' seconds', [
                        'attempt' => $attempt + 1,
                        'max_retries' => $maxRetries
                    ]);
                    
                    sleep((int) $retryAfter);
                    $attempt++;
                    
                    if ($attempt >= $maxRetries) {
                        throw $e;
                    }
                } else {
                    throw $e;
                }
            }
        }
        
        throw new \RuntimeException('Max retries exceeded');
    }

    /**
     * Get all offers with pagination
     *
     * @param int $maxPerPage
     * @return array
     */
    public static function getAllOffers(int $maxPerPage = 100): array
    {
        $request = new \Mirakl\MMP\Shop\Request\Offer\GetOffersRequest();
        return self::fetchAllPaginated($request, $maxPerPage);
    }

    /**
     * Get all products with pagination
     *
     * @param int $maxPerPage
     * @return array
     */
    public static function getAllProducts(int $maxPerPage = 100): array
    {
        $request = new \Mirakl\MMP\Shop\Request\Product\GetProductsRequest();
        return self::fetchAllPaginated($request, $maxPerPage);
    }

    /**
     * Get all orders with pagination
     *
     * @param \DateTime|null $startDate
     * @param \DateTime|null $endDate
     * @param int $maxPerPage
     * @return array
     */
    public static function getAllOrders(
        ?\DateTime $startDate = null,
        ?\DateTime $endDate = null,
        int $maxPerPage = 100
    ): array {
        $request = new \Mirakl\MMP\Shop\Request\Order\GetOrdersRequest();
        
        if ($startDate) {
            $request->setStartDate($startDate);
        }
        
        if ($endDate) {
            $request->setEndDate($endDate);
        }
        
        return self::fetchAllPaginated($request, $maxPerPage);
    }

    /**
     * Safe JSON decode with error handling
     *
     * @param string $json
     * @return array|null
     */
    public static function safeJsonDecode(string $json): ?array
    {
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('JSON decode error: ' . json_last_error_msg(), [
                'json' => $json
            ]);
            return null;
        }
        
        return $data;
    }
}
