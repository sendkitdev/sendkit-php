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
        return $this->request('POST', '/emails/validate', ['email' => $email]);
    }
}
