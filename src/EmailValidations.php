<?php

declare(strict_types=1);

namespace SendKit;

use SendKit\Exceptions\SendKitException;

class EmailValidations extends Service
{
    /**
     * Validate an email address.
     *
     * @return array{
     *     email: string,
     *     is_valid: string,
     *     evaluations: array{
     *         has_valid_syntax: string,
     *         has_valid_dns: string,
     *         mailbox_exists: string,
     *         is_role_address: string,
     *         is_disposable: string,
     *         is_random_input: string,
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
        return $this->request('POST', '/emails/validate', ['email' => $email]);
    }
}
