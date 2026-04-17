<?php

namespace App\Events;

use App\Models\Listing;

final readonly class PriceChanged
{
    public function __construct(
        public Listing $listing,
        public float $oldPrice,
        public float $newPrice,
    ) {}
}
