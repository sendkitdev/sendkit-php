<?php

declare(strict_types=1);

namespace SendKit;

class SendKit
{
    public static function client(string $apiKey, string $baseUrl = 'https://api.sendkit.com'): Client
    {
        return new Client($apiKey, $baseUrl);
    }
}
