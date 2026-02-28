<?php

declare(strict_types=1);

namespace SendKit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;

class Client
{
    private readonly ClientInterface $http;

    private ?Emails $emails = null;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.sendkit.dev',
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new GuzzleClient([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function emails(): Emails
    {
        return $this->emails ??= new Emails($this->http);
    }
}
