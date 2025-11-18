<?php

namespace App\Containers\Mirakl\Models;

use App\Ship\Parents\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Example Model for Porto Pattern
 * Place this in: app/Containers/Mirakl/Models/Offer.php
 *
 * Migration example:
 *
 * Schema::create('mirakl_offers', function (Blueprint $table) {
 *     $table->id();
 *     $table->string('mirakl_id')->unique();
 *     $table->string('sku')->index();
 *     $table->decimal('price', 10, 2);
 *     $table->integer('quantity')->default(0);
 *     $table->string('state')->index();
 *     $table->boolean('active')->default(true)->index();
 *     $table->json('raw_data')->nullable();
 *     $table->timestamp('mirakl_updated_at')->nullable();
 *     $table->timestamps();
 *     $table->softDeletes();
 *
 *     $table->index(['sku', 'active']);
 *     $table->index(['state', 'active']);
 * });
 */
class Offer extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mirakl_offers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'mirakl_id',
        'sku',
        'price',
        'quantity',
        'state',
        'active',
        'raw_data',
        'mirakl_updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'active' => 'boolean',
        'raw_data' => 'array',
        'mirakl_updated_at' => 'datetime',
    ];

    /**
     * Scope a query to only include active offers.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope a query to filter by state.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $state
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByState($query, string $state)
    {
        return $query->where('state', $state);
    }

    /**
     * Scope a query to filter by SKU.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $sku
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySku($query, string $sku)
    {
        return $query->where('sku', $sku);
    }

    /**
     * Get offers with low stock
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $threshold
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLowStock($query, int $threshold = 5)
    {
        return $query->where('quantity', '<=', $threshold)
            ->where('active', true);
    }

    /**
     * Get offers updated recently
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $hours
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecentlyUpdated($query, int $hours = 24)
    {
        return $query->where('mirakl_updated_at', '>=', now()->subHours($hours));
    }

    /**
     * Update or create offer from Mirakl data
     *
     * @param \Mirakl\MMP\Shop\Domain\Offer\ShopOffer $miraklOffer
     * @return self
     */
    public static function createFromMirakl($miraklOffer): self
    {
        return self::updateOrCreate(
            ['mirakl_id' => $miraklOffer->getId()],
            [
                'sku' => $miraklOffer->getProductSku(),
                'price' => $miraklOffer->getPrice(),
                'quantity' => $miraklOffer->getQuantity(),
                'state' => $miraklOffer->getState(),
                'active' => $miraklOffer->getActive(),
                'mirakl_updated_at' => $miraklOffer->getUpdateDate(),
                'raw_data' => $miraklOffer->toArray(),
            ]
        );
    }
}
