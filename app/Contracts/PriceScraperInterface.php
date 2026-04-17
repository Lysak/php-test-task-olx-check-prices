<?php

declare(strict_types=1);

namespace App\Contracts;

interface PriceScraperInterface
{
    public function fetchPrice(string $url): ?float;
}
