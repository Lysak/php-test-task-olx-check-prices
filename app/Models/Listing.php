<?php

namespace App\Models;

use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $url
 * @property string|null $title
 * @property-read float|null $current_price
 * @property Carbon|null $last_checked_at
 * @property int $consecutive_failures
 * @property Carbon|null $deactivated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    protected $fillable = [
        'url',
        'title',
        'current_price',
        'last_checked_at',
        'consecutive_failures',
        'deactivated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // decimal:2 returns string; float cast is overridden by the accessor below to guarantee float precision
            'current_price' => 'float',
            'last_checked_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /** @param Builder<Listing> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('deactivated_at');
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function activeSubscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->whereNotNull('verified_at');
    }

    /** @return HasMany<PriceHistory, $this> */
    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    /**
     * Laravel's built-in casts don't provide a float rounded to N decimal places.
     * decimal:2 returns a string; float cast doesn't round. This accessor ensures
     * price comparisons (===) work correctly without floating-point drift.
     *
     * @noinspection PhpUnused
     *
     * @return Attribute<float|null, never>
     */
    protected function currentPrice(): Attribute
    {
        return Attribute::make(
            get: static fn ($value): ?float => $value !== null
                ? round((float) $value, 2)
                : null,
        );
    }
}
