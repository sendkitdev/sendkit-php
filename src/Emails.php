<?php

declare(strict_types=1);

namespace SendKit;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use SendKit\Exceptions\SendKitException;

class Emails
{
    public function __construct(
        private readonly ClientInterface $http,
    ) {}

    /**
     * Send an email using structured parameters.
     *
     * @param  array{
     *     from: string,
     *     to: string|string[],
     *     subject: string,
     *     html?: string,
     *     text?: string,
     *     cc?: string[],
     *     bcc?: string[],
     *     reply_to?: string,
     *     headers?: array<string, string>,
     *     tags?: array<int, array{name: string, value: string}>,
     *     scheduled_at?: string,
     *     attachments?: array<int, array{filename: string, content: string, content_type?: string}>,
     * }  $params
     * @return array{id: string}
     *
     * @throws SendKitException
     */
    public function send(array $params): array
    {
        $params = array_filter($params, fn ($v) => $v !== null);

        return $this->request('POST', '/emails', $params);
    }

    /**
     * Send an email using a raw MIME message.
     *
     * @return array{id: string}
     *
     * @throws SendKitException
     */
    public function sendMime(string $envelopeFrom, string $envelopeTo, string $rawMessage): array
    {
        return $this->request('POST', '/emails/mime', [
            'envelope_from' => $envelopeFrom,
            'envelope_to' => $envelopeTo,
            'raw_message' => $rawMessage,
        ]);
    }

    /**
     * @throws SendKitException
     */
    private function request(string $method, string $uri, array $data): array
    {
        try {
            $response = $this->http->request($method, $uri, [
                'json' => $data,
            ]);

            /** @var array{id: string} */
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
