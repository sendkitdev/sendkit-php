<?php

declare(strict_types=1);

namespace SendKit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;

class Client
{
    private readonly ClientInterface $http;

    private ?Emails $emails = null;

    private ?EmailValidations $emailValidations = null;

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

    public function emailValidations(): EmailValidations
    {
        return $this->emailValidations ??= new EmailValidations($this->http);
    }

    /**
     * Validate an email address.
     *
     * @return array{
     *     email: string,
     *     is_valid: bool,
     *     evaluations: array{
     *         has_valid_syntax: bool,
     *         has_valid_dns: bool,
     *         mailbox_exists: bool,
     *         is_role_address: bool,
     *         is_disposable: bool,
     *         is_random_input: bool,
     *     },
     *     should_block: bool,
     *     block_reason: string|null,
     *     validated_at: string,
     * }
     *
     * @throws \SendKit\Exceptions\SendKitException
     */
    public function validateEmail(string $email): array
    {
        return $this->emailValidations()->validate($email);
    }
}
