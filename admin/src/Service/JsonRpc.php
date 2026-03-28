<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

class JsonRpc
{
    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const INTERNAL_ERROR = -32603;
    public const FORBIDDEN = -32000;
    public const UNAUTHORIZED = -32001;
    public const RATE_LIMITED = -32002;

    public static function errorResponse(mixed $id, int $code, string $message, array $details = []): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
                'data' => $details,
            ],
        ];
    }

    public static function successResponse(mixed $id, mixed $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    public static function parseRequest(string $body): ?array
    {
        $payload = json_decode($body, true);
        if (!is_array($payload) || !isset($payload['jsonrpc']) || $payload['jsonrpc'] !== '2.0') {
            return null;
        }
        return $payload;
    }
}

