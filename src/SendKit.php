<?php

declare(strict_types=1);

namespace SendKit;

class SendKit
{
    /**
     * @throws Exceptions\SendKitException
     */
    public static function client(string $apiKey = '', string $baseUrl = 'https://api.sendkit.dev'): Client
    {
        return new Client($apiKey, $baseUrl);
    }
}
