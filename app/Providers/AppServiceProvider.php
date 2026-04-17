<?php

namespace App\Providers;

use App\Contracts\PriceScraperInterface;
use App\Services\OlxScraperService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        EventServiceProvider::disableEventDiscovery();

        $this->app->bind(ClientInterface::class, static fn () => new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
        ]));

        $this->app->bind(RequestFactoryInterface::class, static fn () => new HttpFactory);

        $this->app->bind(PriceScraperInterface::class, OlxScraperService::class);
    }

    public function boot(RateLimiter $rateLimiter): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        $rateLimiter->for('mail', static function (): Limit {
            return Limit::perMinute((int) config('mail.rate_limit_per_minute', 40));
        });
    }
}
