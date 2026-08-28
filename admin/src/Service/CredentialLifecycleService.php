<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

/**
 * Owns the credential lifecycle invariants: issuance, listing, and revocation.
 *
 * The plaintext bearer token is returned to the caller exactly once, at
 * issuance. Only the verifier and the encrypted API token are ever handed
 * to the store.
 */
final class CredentialLifecycleService
{
    public function __construct(
        private readonly CredentialLifecycleStoreInterface $store,
        private readonly CredentialCipher $cipher,
    ) {
    }

    /**
     * @return array{id:string,bearer_token:string}
     */
    public function issue(int $ownerId, string $ownerName, string $apiToken, int $expiresAt, ?int $now = null): array
    {
        $now ??= time();

        if ($ownerId <= 0) {
            throw new \InvalidArgumentException('Owner id must be a positive integer');
        }
        if (trim($ownerName) === '') {
            throw new \InvalidArgumentException('Owner name must not be blank');
        }
        if (trim($apiToken) === '') {
            throw new \InvalidArgumentException('API token must not be blank');
        }
        if ($expiresAt <= $now) {
            throw new \InvalidArgumentException('Expiry must be after the current time');
        }

        $credential = McpCredential::issue();
        $encryptedToken = $this->cipher->encrypt($apiToken);

        $id = $this->store->save([
            'owner_id' => $ownerId,
            'owner_name' => $ownerName,
            'selector' => $credential['selector'],
            'verifier' => $credential['verifier'],
            'encrypted_token' => $encryptedToken,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);

        return [
            'id' => $id,
            'bearer_token' => $credential['token'],
        ];
    }

    /**
     * @return list<array{id:string,owner_id:int,owner_name:string,selector:string,expires_at:int,created_at:int,revoked:bool}>
     */
    public function listForOwner(int $ownerId): array
    {
        return $this->store->listByOwner($ownerId);
    }

    public function revoke(string $id, int $actingOwnerId, bool $actingIsAdmin = false): void
    {
        $record = $this->store->findOwnership($id);
        if ($record === null) {
            throw new \RuntimeException('Credential not found');
        }
        if (!$actingIsAdmin && $record['owner_id'] !== $actingOwnerId) {
            throw new \RuntimeException('Not authorized to revoke this credential');
        }

        $this->store->revoke($id);
    }
}
