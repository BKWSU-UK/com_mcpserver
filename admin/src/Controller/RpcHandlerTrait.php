<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Mcpserver\Administrator\Extension\McpserverComponent;
use Joomla\Component\Mcpserver\Administrator\Service\AuthService;
use Joomla\Component\Mcpserver\Administrator\Service\CacheService;
use Joomla\Component\Mcpserver\Administrator\Service\JoomlaCache;
use Joomla\Component\Mcpserver\Administrator\Service\JsonRpc;
use Joomla\Component\Mcpserver\Administrator\Service\MetricsService;
use Joomla\Component\Mcpserver\Administrator\Service\MonologFactory;
use Joomla\Component\Mcpserver\Administrator\Service\PolicyService;
use Joomla\Component\Mcpserver\Administrator\Service\PromptRegistry;
use Joomla\Component\Mcpserver\Administrator\Service\RateLimiter;
use Joomla\Component\Mcpserver\Administrator\Service\RestClient;
use Joomla\Component\Mcpserver\Administrator\Service\RpcService;
use Joomla\Component\Mcpserver\Administrator\Service\SchemaValidator;
use Joomla\Component\Mcpserver\Administrator\Service\ToolRegistry;
use Joomla\Registry\Registry;
use Psr\Log\LoggerInterface;

/**
 * Shared RPC request handling logic for both admin and site controllers.
 *
 * Services are resolved from the DI container (registered in provider.php)
 * when available, with fallback to direct instantiation.
 */
trait RpcHandlerTrait
{
    public function sse(): void
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_mcpserver');

        $this->handleCors($params);

        $authService = $this->resolveService(AuthService::class) ?? new AuthService($params);
        $clientIp    = $authService->getClientIp() ?: 'unknown';

        // Throttle stream establishment before auth: each SSE connection holds a
        // PHP worker for the lifetime below, so unbounded opens are a DoS vector.
        $rateLimiter = $this->resolveService(RateLimiter::class) ?? $this->createRateLimiter($params);
        $rateLimit = $rateLimiter->checkLimit($clientIp);
        if ($rateLimit !== null) {
            header('Content-Type: application/json; charset=utf-8');
            header('Retry-After: ' . $rateLimit['retry_after']);
            http_response_code(429);
            echo json_encode(JsonRpc::errorResponse(null, JsonRpc::RATE_LIMITED, 'Rate limit exceeded'));
            $app->close();
            return;
        }

        $authError = $authService->authenticate();
        if ($authError !== null) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($authError['code'] === JsonRpc::UNAUTHORIZED ? 401 : 403);
            echo json_encode(JsonRpc::errorResponse(null, $authError['code'], $authError['error']));
            $app->close();
            return;
        }

        $sessionId = bin2hex(random_bytes(16));

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $postUrl = Uri::root() . 'index.php?option=com_mcpserver&task=rpc.handle&sessionId=' . $sessionId;

        echo "event: endpoint\n";
        echo "data: " . $postUrl . "\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();

        $cache = new JoomlaCache('mcp_sse');

        // Deterministically reclaim orphaned SSE responses left by sessions whose
        // consumer disconnected before reading. Safe here because this instance keeps
        // its default lifetime (we never call set()/setLifeTime() on it) — gc() only
        // removes entries already past the global cachetime.
        $cache->gc();

        $startTime = time();
        $lastPingTime = $startTime;

        // Cap the worker hold time. Clients using EventSource reconnect
        // automatically, so a short cap bounds resource use without breaking
        // long-lived sessions.
        $timeout = 300;

        while (time() - $startTime < $timeout) {
            if (connection_aborted()) {
                break;
            }

            $message = $cache->get($sessionId);
            if ($message) {
                echo "event: message\n";
                echo "data: " . $message . "\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                $cache->delete($sessionId);
            }

            if ((time() - $lastPingTime) >= 15) {
                echo ": keep-alive\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                $lastPingTime = time();
            }

            usleep(200000);
        }

        // Drop this session's own entry in case a response was written into the gap
        // between the final poll and loop exit, so it is not left for gc to reclaim.
        $cache->delete($sessionId);

        $app->close();
    }

    public function handle(): void
    {
        $app = Factory::getApplication();
        $sessionId = $app->input->get('sessionId', '', 'string');

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($sessionId)) {
            $this->sse();
            return;
        }

        $startTime = microtime(true);
        $context   = $app->getName() === 'administrator' ? 'admin' : 'site';

        header('Content-Type: application/json; charset=utf-8');

        $params = ComponentHelper::getParams('com_mcpserver');

        $this->handleCors($params);

        $authService = $this->resolveService(AuthService::class) ?? new AuthService($params);
        $clientIp    = $authService->getClientIp() ?: 'unknown';

        // Rate limit BEFORE authentication so failed auth attempts (bearer-token
        // guessing) are throttled too. Keyed on the proxy-aware client IP.
        $rateLimiter = $this->resolveService(RateLimiter::class) ?? $this->createRateLimiter($params);
        $rateLimit = $rateLimiter->checkLimit($clientIp);
        if ($rateLimit !== null) {
            header('Retry-After: ' . $rateLimit['retry_after']);
            http_response_code(429);
            echo json_encode(JsonRpc::errorResponse(null, JsonRpc::RATE_LIMITED, 'Rate limit exceeded'));
            $this->recordMetric($startTime, '', '', 'rate_limited', JsonRpc::RATE_LIMITED, 429, $clientIp, $context);
            $app->close();
            return;
        }

        $authError = $authService->authenticate();
        if ($authError !== null) {
            $code = $authError['code'] === JsonRpc::UNAUTHORIZED ? 401 : 403;
            http_response_code($code);
            echo json_encode(JsonRpc::errorResponse(null, $authError['code'], $authError['error']));
            $this->recordMetric($startTime, '', '', 'auth_failed', $authError['code'], $code, $clientIp, $context);
            $app->close();
            return;
        }

        $body = file_get_contents('php://input') ?: '';
        $decoded = json_decode($body, true);

        $rpcService = $this->resolveService(RpcService::class) ?? $this->createRpcService($params);

        if (JsonRpc::isBatch($decoded)) {
            $this->handleBatch($decoded, $rpcService, $startTime, $clientIp, $context, $sessionId);
            return;
        }

        $request = JsonRpc::parseRequestData($decoded);

        if ($request === null) {
            http_response_code(400);
            echo json_encode(JsonRpc::errorResponse(null, JsonRpc::INVALID_REQUEST, 'Invalid JSON-RPC 2.0 request'));
            $this->recordMetric($startTime, '', '', 'invalid_request', JsonRpc::INVALID_REQUEST, 400, $clientIp, $context);
            $app->close();
            return;
        }

        $method   = (string) ($request['method'] ?? '');
        $toolName = $this->extractToolName($request);

        $response = $rpcService->handle($request);

        // Policy denials (disabled tool, read-only mode) are MCP tool results
        // with isError=true, not JSON-RPC errors, so they are invisible in the
        // response envelope — ask the service so they are not logged as 'ok'.
        $okStatus = $rpcService->wasLastCallBlocked() ? 'blocked' : 'ok';

        if ($response === null) {
            http_response_code(204);
            $this->recordMetric($startTime, $method, $toolName, $okStatus, null, 204, $clientIp, $context);
            $app->close();
            return;
        }

        $httpStatus = 200;
        if (isset($response['error'])) {
            $httpStatus = match ($response['error']['code']) {
                JsonRpc::UNAUTHORIZED => 401,
                JsonRpc::RATE_LIMITED => 429,
                default => 200,
            };
        }

        $this->recordMetric(
            $startTime,
            $method,
            $toolName,
            isset($response['error']) ? 'error' : $okStatus,
            $response['error']['code'] ?? null,
            $httpStatus,
            $clientIp,
            $context
        );

        $jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!empty($sessionId)) {
            $sseCache = new JoomlaCache('mcp_sse');
            $sseCache->set($sessionId, $jsonResponse, 30);
            http_response_code(202);
            echo json_encode(['status' => 'accepted', 'sessionId' => $sessionId]);
        } else {
            http_response_code($httpStatus);
            echo $jsonResponse;
        }

        $app->close();
    }

    /**
     * Handle a JSON-RPC 2.0 batch request (required by MCP protocol revision
     * 2025-03-26; removed again in 2025-06-18 but harmless to keep supporting).
     * Entries are dispatched independently; notification entries produce no
     * response, and an all-notification batch yields 204.
     */
    private function handleBatch(
        array $batch,
        RpcService $rpcService,
        float $startTime,
        string $clientIp,
        string $context,
        string $sessionId
    ): void {
        $app = Factory::getApplication();
        $responses = [];

        foreach ($batch as $entry) {
            $request = JsonRpc::parseRequestData($entry);

            if ($request === null) {
                $responses[] = JsonRpc::errorResponse(null, JsonRpc::INVALID_REQUEST, 'Invalid JSON-RPC 2.0 request');
                $this->recordMetric($startTime, '', '', 'invalid_request', JsonRpc::INVALID_REQUEST, 200, $clientIp, $context);
                continue;
            }

            $response = $rpcService->handle($request);

            // See handle(): policy denials are tool results, not JSON-RPC errors.
            $okStatus = $rpcService->wasLastCallBlocked() ? 'blocked' : 'ok';

            $this->recordMetric(
                $startTime,
                (string) ($request['method'] ?? ''),
                $this->extractToolName($request),
                isset($response['error']) ? 'error' : $okStatus,
                $response['error']['code'] ?? null,
                200,
                $clientIp,
                $context
            );

            if ($response !== null) {
                $responses[] = $response;
            }
        }

        if (empty($responses)) {
            http_response_code(204);
            $app->close();
            return;
        }

        $jsonResponse = json_encode($responses, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!empty($sessionId)) {
            $sseCache = new JoomlaCache('mcp_sse');
            $sseCache->set($sessionId, $jsonResponse, 30);
            http_response_code(202);
            echo json_encode(['status' => 'accepted', 'sessionId' => $sessionId]);
        } else {
            http_response_code(200);
            echo $jsonResponse;
        }

        $app->close();
    }

    private function handleCors(Registry $params): void
    {
        $allowedOrigins = array_filter(array_map('trim', explode(',', (string) $params->get('allowed_origins', ''))));

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (!empty($allowedOrigins) && !empty($origin) && in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: POST, OPTIONS');
        // Mcp-Session-Id / MCP-Protocol-Version are sent by Streamable HTTP MCP
        // clients; without them here, browser-based clients fail CORS preflight.
        header('Access-Control-Allow-Headers: Content-Type, Authorization, Mcp-Session-Id, MCP-Protocol-Version');
        header('Access-Control-Max-Age: 3600');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Content-Length: 0');
            http_response_code(204);
            Factory::getApplication()->close();
        }
    }

    /**
     * Resolve a service from the DI container if available.
     */
    private function resolveService(string $className): ?object
    {
        $container = McpserverComponent::getServiceContainer();
        if ($container !== null && $container->has($className)) {
            return $container->get($className);
        }

        return null;
    }

    /**
     * Record a single request to the metrics log. Resilient: recording is
     * internally guarded (enabled check + try/catch in MetricsService), so this
     * never disrupts the response or prevents $app->close() from running.
     */
    private function recordMetric(
        float $startTime,
        string $method,
        string $toolName,
        string $status,
        ?int $errorCode,
        int $httpStatus,
        string $clientIp,
        string $context
    ): void {
        $metrics = $this->resolveService(MetricsService::class)
            ?? $this->createMetricsService(ComponentHelper::getParams('com_mcpserver'));

        $metrics->record([
            'created'     => Factory::getDate()->toSql(),
            'method'      => $method,
            'tool_name'   => $toolName,
            'status'      => $status,
            'error_code'  => $errorCode,
            'http_status' => $httpStatus,
            'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
            'client_ip'   => $clientIp,
            'context'     => $context,
        ]);
    }

    /**
     * Extract a metrics label: tool name, prompt name, or resource URI.
     */
    private function extractToolName(array $request): string
    {
        $params = is_array($request['params'] ?? null) ? $request['params'] : [];

        return match ($request['method'] ?? '') {
            'tools/call', 'prompts/get' => (string) ($params['name'] ?? ''),
            'resources/read' => (string) ($params['uri'] ?? ''),
            default => '',
        };
    }

    /**
     * Fallback: create MetricsService when DI container is not available.
     */
    private function createMetricsService(Registry $params): MetricsService
    {
        return new MetricsService($params);
    }

    /**
     * Fallback: create RateLimiter when DI container is not available.
     */
    private function createRateLimiter(Registry $params): RateLimiter
    {
        $cacheBackend = new JoomlaCache('com_mcpserver_ratelimit');
        return new RateLimiter(
            $cacheBackend,
            (int) $params->get('rate_limit_requests', 60),
            (int) $params->get('rate_limit_window', 60)
        );
    }

    /**
     * Fallback: create RpcService when DI container is not available.
     */
    private function createRpcService(Registry $params): RpcService
    {
        $baseUrl = rtrim((string) $params->get('base_url', ''), '/');
        $apiToken = (string) $params->get('api_token', '');
        $cacheTtl = (int) $params->get('cache_ttl', 60);
        $verifySsl = (bool) $params->get('verify_ssl', true);
        $resolveIp = trim((string) $params->get('resolve_ip', ''));
        $serverName = (string) $params->get('server_name', 'joomla-mcp-server');

        if ($baseUrl === '') {
            $baseUrl = rtrim(Uri::root(), '/');
        }

        if ($baseUrl !== '' && !preg_match('#^https?://#i', $baseUrl)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $baseUrl = $scheme . '://' . $host . '/' . ltrim($baseUrl, '/');
        }

        $logger = MonologFactory::createComponentLogger('mcpserver', $serverName);
        $rest = new RestClient($baseUrl, $apiToken ?: null, $logger, $verifySsl, $resolveIp !== '' ? $resolveIp : null);
        $cacheBackend = new JoomlaCache('com_mcpserver');
        $cache = new CacheService($cacheBackend, $cacheTtl);
        $policy = new PolicyService(ComponentHelper::getParams('com_mcpserver'));
        $toolRegistry = new ToolRegistry();
        $promptRegistry = new PromptRegistry();
        $validator = new SchemaValidator();

        return new RpcService(
            $rest,
            $cache,
            $policy,
            $logger,
            $toolRegistry,
            $validator,
            $promptRegistry,
            $serverName,
            (int) $params->get('tools_list_page_size', 100)
        );
    }
}
