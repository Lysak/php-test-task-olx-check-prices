<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Psr\Http\Message\ResponseInterface;

class RequestFailedException extends Exception
{
    public function __construct(private readonly ResponseInterface $response)
    {
        parent::__construct(
            \sprintf('HTTP request failed with status %d', $response->getStatusCode()),
            $response->getStatusCode(),
        );
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
