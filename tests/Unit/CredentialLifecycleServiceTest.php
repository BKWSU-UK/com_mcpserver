<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Tests\Unit;

defined('_JEXEC') or die;

use Joomla\Component\Mcpserver\Administrator\Service\CredentialCipher;
use Joomla\Component\Mcpserver\Administrator\Service\CredentialLifecycleService;
use Joomla\Component\Mcpserver\Administrator\Service\CredentialLifecycleStoreInterface;
use Joomla\Component\Mcpserver\Administrator\Service\McpCredential;
use PHPUnit\Framework\TestCase;

/**
 * In-memory fake used to observe exactly what the service persists, without
 * a database. Only the {@see CredentialLifecycleStoreInterface} contract is
 * exercised.
 */
final class InMemoryCredentialLifecycleStore implements CredentialLifecycleStoreInterface
{
    /** @var array<string,array{owner_id:int,owner_name:string,selector:string,verifier:string,encrypted_token:array,expires_at:int,created_at:int,revoked:bool}> */
    public array $records = [];

    private int $nextId = 1;

    public function save(array $record): string
    {
        $id = (string) $this->nextId++;
        $this->records[$id] = $record + ['revoked' => false];
        return $id;
    }

    public function listByOwner(int $ownerId): array
    {
        $result = [];
        foreach ($this->records as $id => $record) {
            if ($record['owner_id'] !== $ownerId) {
                continue;
            }
            $result[] = [
                'id' => $id,
                'owner_id' => $record['owner_id'],
                'owner_name' => $record['owner_name'],
                'selector' => $record['selector'],
                'expires_at' => $record['expires_at'],
                'created_at' => $record['created_at'],
                'revoked' => $record['revoked'],
            ];
        }
        return $result;
    }

    public function findOwnership(string $id): ?array
    {
        if (!isset($this->records[$id])) {
            return null;
        }
        return [
            'id' => $id,
            'owner_id' => $this->records[$id]['owner_id'],
            'revoked' => $this->records[$id]['revoked'],
        ];
    }

    public function revoke(string $id): void
    {
        $this->records[$id]['revoked'] = true;
    }
}

class CredentialLifecycleServiceTest extends TestCase
{
    private const NOW = 1_700_000_000;

    private function cipher(): CredentialCipher
    {
        return new CredentialCipher('joomla-site-secret-value', base64_encode('component-salt-bytes-0123456789'));
    }

    private function service(InMemoryCredentialLifecycleStore $store): CredentialLifecycleService
    {
        return new CredentialLifecycleService($store, $this->cipher());
    }

    public function testIssueReturnsBearerTokenOnceAndStoresOnlyProtectedMaterial(): void
    {
        $store = new InMemoryCredentialLifecycleStore();
        $service = $this->service($store);

        $issued = $service->issue(42, 'Ada Lovelace', 'super-secret-api-token', self::NOW + 3600, self::NOW);

        $this->assertNotNull(McpCredential::parseBearer('Bearer ' . $issued['bearer_token']));

        $stored = $store->records[$issued['id']];
        $this->assertArrayNotHasKey('token', $stored);
        $this->assertArrayNotHasKey('secret', $stored);
        $this->assertArrayNotHasKey('api_token', $stored);
        $this->assertSame(42, $stored['owner_id']);
        $this->assertSame('Ada Lovelace', $stored['owner_name']);

        foreach ($stored as $value) {
            if (is_string($value)) {
                $this->assertStringNotContainsString('super-secret-api-token', $value);
                $this->assertStringNotContainsString($issued['bearer_token'], $value);
            }
        }

        $decrypted = $this->cipher()->decrypt($stored['encrypted_token']);
        $this->assertSame('super-secret-api-token', $decrypted);

        $parsedBearer = McpCredential::parseBearer('Bearer ' . $issued['bearer_token']);
        $this->assertTrue(McpCredential::verify($parsedBearer['secret'], $stored['verifier']));
    }

    public function testIssueRejectsNonPositiveOwnerId(): void
    {
        $service = $this->service(new InMemoryCredentialLifecycleStore());

        $this->expectException(\InvalidArgumentException::class);
        $service->issue(0, 'Ada Lovelace', 'api-token', self::NOW + 3600, self::NOW);
    }

    public function testIssueRejectsBlankOwnerName(): void
    {
        $service = $this->service(new InMemoryCredentialLifecycleStore());

        $this->expectException(\InvalidArgumentException::class);
        $service->issue(1, '   ', 'api-token', self::NOW + 3600, self::NOW);
    }

    public function testIssueRejectsBlankApiToken(): void
    {
        $service = $this->service(new InMemoryCredentialLifecycleStore());

        $this->expectException(\InvalidArgumentException::class);
        $service->issue(1, 'Ada Lovelace', '  ', self::NOW + 3600, self::NOW);
    }

    public function testIssueRejectsExpiryAtOrBeforeNow(): void
    {
        $service = $this->service(new InMemoryCredentialLifecycleStore());

        $this->expectException(\InvalidArgumentException::class);
        $service->issue(1, 'Ada Lovelace', 'api-token', self::NOW, self::NOW);
    }

    public function testListForOwnerExposesMetadataOnly(): void
    {
        $store = new InMemoryCredentialLifecycleStore();
        $service = $this->service($store);
        $service->issue(42, 'Ada Lovelace', 'api-token', self::NOW + 3600, self::NOW);

        $listed = $service->listForOwner(42);

        $this->assertCount(1, $listed);
        $this->assertArrayNotHasKey('verifier', $listed[0]);
        $this->assertArrayNotHasKey('encrypted_token', $listed[0]);
        $this->assertSame(42, $listed[0]['owner_id']);
        $this->assertFalse($listed[0]['revoked']);
    }

    public function testListForOwnerExcludesOtherOwnersCredentials(): void
    {
        $store = new InMemoryCredentialLifecycleStore();
        $service = $this->service($store);
        $service->issue(42, 'Ada Lovelace', 'api-token', self::NOW + 3600, self::NOW);

        $this->assertSame([], $service->listForOwner(99));
    }

    public function testOwnerCanRevokeOwnCredential(): void
    {
        $store = new InMemoryCredentialLifecycleStore();
        $service = $this->service($store);
        $issued = $service->issue(42, 'Ada Lovelace', 'api-token', self::NOW + 3600, self::NOW);

        $service->revoke($issued['id'], 42, false);

        $this->assertTrue($store->records[$issued['id']]['revoked']);
    }

    public function testNonOwnerNonAdminCannotRevoke(): void
    {
        $store = new InMemoryCredentialLifecycleStore();
        $service = $this->service($store);
        $issued = $service->issue(42, 'Ada Lovelace', 'api-token', self::NOW + 3600, self::NOW);

        $this->expectException(\RuntimeException::class);
        $service->revoke($issued['id'], 99, false);
    }

    public function testAdminCanRevokeAnyOwnersCredential(): void
    {
        $store = new InMemoryCredentialLifecycleStore();
        $service = $this->service($store);
        $issued = $service->issue(42, 'Ada Lovelace', 'api-token', self::NOW + 3600, self::NOW);

        $service->revoke($issued['id'], 99, true);

        $this->assertTrue($store->records[$issued['id']]['revoked']);
    }

    public function testRevokeUnknownCredentialFails(): void
    {
        $service = $this->service(new InMemoryCredentialLifecycleStore());

        $this->expectException(\RuntimeException::class);
        $service->revoke('missing', 42, false);
    }
}
