<?php

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
use Joomla\Component\Mcpserver\Administrator\Service\MonologFactory;
use Joomla\Component\Mcpserver\Administrator\Service\PolicyService;
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
        $startTime = time();
        $lastPingTime = $startTime;
        $timeout = 3600;

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

        header('Content-Type: application/json; charset=utf-8');

        $params = ComponentHelper::getParams('com_mcpserver');

        $this->handleCors($params);

        $authService = $this->resolveService(AuthService::class) ?? new AuthService($params);
        $authError = $authService->authenticate();
        if ($authError !== null) {
            http_response_code($authError['code'] === JsonRpc::UNAUTHORIZED ? 401 : 403);
            echo json_encode(JsonRpc::errorResponse(null, $authError['code'], $authError['error']));
            $app->close();
            return;
        }

        $rateLimiter = $this->resolveService(RateLimiter::class) ?? $this->createRateLimiter($params);
        $identifier = $app->input->server->getString('REMOTE_ADDR', 'unknown');
        $rateLimit = $rateLimiter->checkLimit($identifier);
        if ($rateLimit !== null) {
            header('Retry-After: ' . $rateLimit['retry_after']);
            http_response_code(429);
            echo json_encode(JsonRpc::errorResponse(null, JsonRpc::RATE_LIMITED, 'Rate limit exceeded'));
            $app->close();
            return;
        }

        $body = file_get_contents('php://input') ?: '';
        $request = JsonRpc::parseRequest($body);

        if ($request === null) {
            http_response_code(400);
            echo json_encode(JsonRpc::errorResponse(null, JsonRpc::INVALID_REQUEST, 'Invalid JSON-RPC 2.0 request'));
            $app->close();
            return;
        }

        $rpcService = $this->resolveService(RpcService::class) ?? $this->createRpcService($params);
        $response = $rpcService->handle($request);

        if ($response === null) {
            http_response_code(204);
            $app->close();
            return;
        }

        $httpStatus = 200;
        if (isset($response['error'])) {
            $httpStatus = match ($response['error']['code']) {
                JsonRpc::UNAUTHORIZED => 401,
                JsonRpc::FORBIDDEN => 403,
                JsonRpc::METHOD_NOT_FOUND => 404,
                JsonRpc::RATE_LIMITED => 429,
                default => 400,
            };
        }

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

    private function handleCors(Registry $params): void
    {
        $allowedOrigins = array_filter(array_map('trim', explode(',', (string) $params->get('allowed_origins', ''))));

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (!empty($allowedOrigins) && !empty($origin) && in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
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
        $rest = new RestClient($baseUrl, $apiToken ?: null, $logger, $verifySsl);
        $cacheBackend = new JoomlaCache('com_mcpserver');
        $cache = new CacheService($cacheBackend, $cacheTtl);
        $policy = new PolicyService();
        $toolRegistry = new ToolRegistry();
        $validator = new SchemaValidator();

        return new RpcService($rest, $cache, $policy, $logger, $toolRegistry, $validator, $serverName);
    }
}
