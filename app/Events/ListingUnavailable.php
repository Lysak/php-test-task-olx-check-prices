<?php

namespace App\Events;

use App\Models\Listing;

final readonly class ListingUnavailable
{
    public function __construct(public Listing $listing) {}
}
