<?php

declare(strict_types=1);

namespace SendKit;

use SendKit\Exceptions\SendKitException;

class ContactProperties extends Service
{
    /**
     * Create a new contact property.
     *
     * @param  array{
     *     key: string,
     *     type: string,
     *     fallback_value?: string|null,
     * }  $params
     * @return array{data: array{id: string, key: string, type: string, fallback_value: string|null, created_at: string, updated_at: string}}
     *
     * @throws SendKitException
     */
    public function create(array $params): array
    {
        $params = array_filter($params, fn ($v) => $v !== null);

        return $this->request('POST', '/properties', $params);
    }

    /**
     * List all contact properties (paginated).
     *
     * @param  array<string, mixed>  $query  Optional query parameters (e.g. page, per_page).
     * @return array{data: array, links: array, meta: array}
     *
     * @throws SendKitException
     */
    public function list(array $query = []): array
    {
        return $this->request('GET', '/properties', query: $query);
    }

    /**
     * Update a contact property.
     *
     * @param  array{
     *     key?: string,
     *     type?: string,
     *     fallback_value?: string|null,
     * }  $params
     * @return array{data: array{id: string, key: string, type: string, fallback_value: string|null, created_at: string, updated_at: string}}
     *
     * @throws SendKitException
     */
    public function update(string $id, array $params): array
    {
        $params = array_filter($params, fn ($v) => $v !== null);

        return $this->request('PUT', "/properties/{$id}", $params);
    }

    /**
     * Delete a contact property.
     *
     * @throws SendKitException
     */
    public function delete(string $id): void
    {
        $this->request('DELETE', "/properties/{$id}");
    }
}
