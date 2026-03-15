<?php

declare(strict_types=1);

namespace SendKit;

use SendKit\Exceptions\SendKitException;

class Emails extends Service
{
    /**
     * Send an email using structured parameters.
     *
     * @param  array{
     *     from: string,
     *     to: string|string[],
     *     subject: string,
     *     html?: string,
     *     text?: string,
     *     cc?: string|string[],
     *     bcc?: string|string[],
     *     reply_to?: string|string[],
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

        if (isset($params['cc']) && is_string($params['cc'])) {
            $params['cc'] = [$params['cc']];
        }

        if (isset($params['bcc']) && is_string($params['bcc'])) {
            $params['bcc'] = [$params['bcc']];
        }

        if (isset($params['reply_to']) && is_string($params['reply_to'])) {
            $params['reply_to'] = [$params['reply_to']];
        }

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
}
