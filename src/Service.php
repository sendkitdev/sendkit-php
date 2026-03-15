<?php

declare(strict_types=1);

namespace SendKit;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use SendKit\Exceptions\SendKitException;

abstract class Service
{
    public function __construct(
        protected readonly ClientInterface $http,
    ) {}

    /**
     * @param  array<string, mixed>|null  $data
     * @param  array<string, mixed>  $query
     *
     * @throws SendKitException
     */
    protected function request(string $method, string $uri, ?array $data = null, array $query = []): array
    {
        try {
            $options = [];

            if ($data !== null) {
                $options['json'] = $data;
            }

            if ($query !== []) {
                $options['query'] = $query;
            }

            $response = $this->http->request($method, $uri, $options);

            $contents = $response->getBody()->getContents();

            if ($contents === '') {
                return [];
            }

            /** @var array */
            return json_decode($contents, true);
        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode() ?? 500;
            $body = $e->getResponse()?->getBody()->getContents();
            $decoded = $body ? json_decode($body, true) : null;
            $message = $decoded['message'] ?? $e->getMessage();

            throw new SendKitException($message, $status);
        }
    }
}
