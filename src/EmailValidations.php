<?php

declare(strict_types=1);

namespace SendKit;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use SendKit\Exceptions\SendKitException;

class EmailValidations
{
    public function __construct(
        private readonly ClientInterface $http,
    ) {}

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
     * @throws SendKitException
     */
    public function validate(string $email): array
    {
        try {
            $response = $this->http->request('POST', '/v1/emails/validate', [
                'json' => ['email' => $email],
            ]);

            /** @var array{email: string, is_valid: bool, evaluations: array, should_block: bool, block_reason: string|null, validated_at: string} */
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode() ?? 500;
            $body = $e->getResponse()?->getBody()->getContents();
            $decoded = $body ? json_decode($body, true) : null;
            $message = $decoded['message'] ?? $e->getMessage();

            throw new SendKitException($message, $status);
        }
    }
}
