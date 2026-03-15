<?php

declare(strict_types=1);

namespace SendKit;

use SendKit\Exceptions\SendKitException;

class Contacts extends Service
{
    /**
     * Create or update a contact by email (upsert).
     *
     * @param  array{
     *     email: string,
     *     first_name?: string|null,
     *     last_name?: string|null,
     *     user_id?: string|null,
     *     unsubscribed?: bool,
     *     list_ids?: string[],
     *     properties?: array<string, string|null>,
     * }  $params
     * @return array{data: array{id: string, email: string, first_name: string|null, last_name: string|null, user_id: string|null, unsubscribed: bool, properties: array<string, string>, lists: array<int, array{id: string, name: string, created_at: string, updated_at: string}>, created_at: string, updated_at: string}}
     *
     * @throws SendKitException
     */
    public function create(array $params): array
    {
        $params = array_filter($params, fn ($v) => $v !== null);

        return $this->request('POST', '/contacts', $params);
    }

    /**
     * List all contacts (paginated).
     *
     * @param  array<string, mixed>  $query  Optional query parameters (e.g. page, per_page).
     * @return array{data: array, links: array, meta: array}
     *
     * @throws SendKitException
     */
    public function list(array $query = []): array
    {
        return $this->request('GET', '/contacts', query: $query);
    }

    /**
     * Get a single contact.
     *
     * @return array{data: array{id: string, email: string, first_name: string|null, last_name: string|null, user_id: string|null, unsubscribed: bool, properties: array<string, string>, lists: array<int, array{id: string, name: string, created_at: string, updated_at: string}>, created_at: string, updated_at: string}}
     *
     * @throws SendKitException
     */
    public function get(string $id): array
    {
        return $this->request('GET', "/contacts/{$id}");
    }

    /**
     * Update a contact.
     *
     * @param  array{
     *     email?: string,
     *     first_name?: string|null,
     *     last_name?: string|null,
     *     user_id?: string|null,
     *     unsubscribed?: bool,
     *     list_ids?: string[],
     *     properties?: array<string, string|null>,
     * }  $params
     * @return array{data: array{id: string, email: string, first_name: string|null, last_name: string|null, user_id: string|null, unsubscribed: bool, properties: array<string, string>, lists: array<int, array{id: string, name: string, created_at: string, updated_at: string}>, created_at: string, updated_at: string}}
     *
     * @throws SendKitException
     */
    public function update(string $id, array $params): array
    {
        $params = array_filter($params, fn ($v) => $v !== null);

        return $this->request('PUT', "/contacts/{$id}", $params);
    }

    /**
     * Delete a contact.
     *
     * @throws SendKitException
     */
    public function delete(string $id): void
    {
        $this->request('DELETE', "/contacts/{$id}");
    }

    /**
     * Add a contact to one or more lists.
     *
     * @param  string[]  $listIds
     * @return array{data: array{id: string, email: string, first_name: string|null, last_name: string|null, user_id: string|null, unsubscribed: bool, properties: array<string, string>, lists: array<int, array{id: string, name: string, created_at: string, updated_at: string}>, created_at: string, updated_at: string}}
     *
     * @throws SendKitException
     */
    public function addToLists(string $id, array $listIds): array
    {
        return $this->request('POST', "/contacts/{$id}/lists", [
            'list_ids' => $listIds,
        ]);
    }

    /**
     * List a contact's lists (paginated).
     *
     * @param  array<string, mixed>  $query  Optional query parameters (e.g. page, per_page).
     * @return array{data: array, links: array, meta: array}
     *
     * @throws SendKitException
     */
    public function listLists(string $id, array $query = []): array
    {
        return $this->request('GET', "/contacts/{$id}/lists", query: $query);
    }

    /**
     * Remove a contact from a list.
     *
     * @throws SendKitException
     */
    public function removeFromList(string $id, string $listId): void
    {
        $this->request('DELETE', "/contacts/{$id}/lists/{$listId}");
    }
}
