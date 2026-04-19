<?php

namespace Tests\Unit;

use App\Services\OlxScraperService;
use Illuminate\Cache\RateLimiter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

class OlxScraperServiceTest extends TestCase
{
    private ClientInterface&MockObject $httpClient;

    private RequestFactoryInterface&MockObject $requestFactory;

    private LoggerInterface&MockObject $logger;

    private OlxScraperService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->method('attempt')->willReturn(true);

        $this->service = new OlxScraperService(
            $this->httpClient,
            $this->requestFactory,
            $this->logger,
            $rateLimiter,
            \PHP_INT_MAX, // eliminates usleep delay in tests
        );
    }

    public function test_returns_price_from_valid_ld_json(): void
    {
        $this->mockHtmlResponse($this->buildHtml(price: 1300.0));

        $result = $this->service->fetchPrice('https://www.olx.ua/ad');

        $this->assertSame(1300.0, $result);
    }

    public function test_returns_null_when_ld_json_script_not_found(): void
    {
        $this->mockHtmlResponse('<html><body><p>No structured data</p></body></html>');
        $this->logger->expects($this->once())->method('warning');

        $result = $this->service->fetchPrice('https://www.olx.ua/ad');

        $this->assertNull($result);
    }

    public function test_returns_null_when_offers_price_missing_in_ld_json(): void
    {
        $html = $this->buildHtml(data: ['@type' => 'Product', 'name' => 'Test']);
        $this->mockHtmlResponse($html);
        $this->logger->expects($this->once())->method('warning');

        $result = $this->service->fetchPrice('https://www.olx.ua/ad');

        $this->assertNull($result);
    }

    public function test_returns_null_when_ld_json_is_not_valid_json(): void
    {
        $html = '<html><body><script type="application/ld+json">not-valid-json</script></body></html>';
        $this->mockHtmlResponse($html);
        $this->logger->expects($this->once())->method('warning');

        $result = $this->service->fetchPrice('https://www.olx.ua/ad');

        $this->assertNull($result);
    }

    public function test_returns_null_and_logs_error_on_http_exception(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturnSelf();
        $this->requestFactory->method('createRequest')->willReturn($request);

        $this->httpClient
            ->method('sendRequest')
            ->willThrowException($this->createMock(ClientExceptionInterface::class));

        $this->logger->expects($this->once())->method('error');

        $result = $this->service->fetchPrice('https://www.olx.ua/ad');

        $this->assertNull($result);
    }

    private function mockHtmlResponse(string $html): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturnSelf();

        $body = $this->createMock(StreamInterface::class);
        $body->method('getContents')->willReturn($html);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);

        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($response);
    }

    /** @param array<string, mixed>|null $data */
    private function buildHtml(?float $price = null, ?array $data = null): string
    {
        $payload = $data ?? [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => 'Test listing',
            'offers' => [
                '@type' => 'Offer',
                'price' => $price,
                'priceCurrency' => 'USD',
            ],
        ];

        return '<html><body>'
            . '<script type="application/ld+json">' . json_encode($payload) . '</script>'
            . '</body></html>';
    }
}
