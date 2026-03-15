<?php

declare(strict_types=1);

namespace SendKit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use SendKit\Exceptions\SendKitException;

class Client
{
    private readonly ClientInterface $http;

    private readonly string $apiKey;

    private ?Emails $emails = null;

    private ?Contacts $contacts = null;

    private ?ContactProperties $contactProperties = null;

    private ?EmailValidations $emailValidations = null;

    /**
     * @throws SendKitException
     */
    public function __construct(
        string $apiKey = '',
        private readonly string $baseUrl = 'https://api.sendkit.dev',
        ?ClientInterface $http = null,
    ) {
        $resolvedKey = $apiKey !== '' ? $apiKey : (getenv('SENDKIT_API_KEY') ?: '');

        if ($resolvedKey === '') {
            throw new SendKitException('The SendKit API key is not set. Provide it via the constructor or the SENDKIT_API_KEY environment variable.', 0, 'missing_api_key');
        }

        $this->apiKey = $resolvedKey;

        $this->http = $http ?? new GuzzleClient([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function contacts(): Contacts
    {
        return $this->contacts ??= new Contacts($this->http);
    }

    public function contactProperties(): ContactProperties
    {
        return $this->contactProperties ??= new ContactProperties($this->http);
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
