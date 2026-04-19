<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PriceScraperInterface;
use App\Exceptions\RequestFailedException;
use App\Support\RateLimiterKey;
use Illuminate\Cache\RateLimiter;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class OlxScraperService implements PriceScraperInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private LoggerInterface $logger,
        private RateLimiter $rateLimiter,
        private int $rateLimitPerMinute,
    ) {}

    public function fetchPrice(string $url): ?float
    {
        $this->throttle();

        try {
            $request = $this->requestFactory
                ->createRequest(Request::METHOD_GET, $url)
                ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36')
                ->withHeader('Accept-Language', 'uk-UA,uk;q=0.9');

            $response = $this->httpClient->sendRequest($request);

            if (
                // 2xx range
                $response->getStatusCode() < Response::HTTP_OK
                || $response->getStatusCode() >= Response::HTTP_MULTIPLE_CHOICES
            ) {
                throw new RequestFailedException($response);
            }

            $html = $response->getBody()->getContents();

            return $this->extractPriceFromHtml($html);
        } catch (RequestFailedException $e) {
            $this->logger->error('OLX scraper bad response', ['url' => $url, 'status' => $e->getCode()]);

            return null;
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('OLX scraper HTTP error', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function throttle(): void
    {
        usleep((int) (60_000_000 * 0.9 / $this->rateLimitPerMinute));

        while (! $this->rateLimiter->attempt(RateLimiterKey::OLX, $this->rateLimitPerMinute, static fn () => true)) {
            sleep(max(1, $this->rateLimiter->availableIn(RateLimiterKey::OLX)));
        }
    }

    private function extractPriceFromHtml(string $html): ?float
    {
        if (! preg_match('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $html, $matches)) {
            $this->logger->warning('OLX scraper: ld+json script not found');

            return null;
        }

        $data = json_decode($matches[1], true);

        if (! \is_array($data)) {
            $this->logger->warning('OLX scraper: failed to decode ld+json');

            return null;
        }

        $rawPrice = data_get($data, 'offers.price');

        if ($rawPrice === null) {
            $this->logger->warning('OLX scraper: price not found in ld+json');

            return null;
        }

        return (float) $rawPrice;
    }
}
