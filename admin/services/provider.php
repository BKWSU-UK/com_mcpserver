<?php

declare(strict_types=1);

defined('_JEXEC') or die;

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Dispatcher\DispatcherInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\MVCFactory as MVCFactoryProvider;
use Joomla\CMS\Extension\Service\Provider\RouterFactory as RouterFactoryProvider;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Mcpserver\Administrator\Dispatcher\Dispatcher;
use Joomla\Component\Mcpserver\Administrator\Extension\McpserverComponent;
use Joomla\Component\Mcpserver\Administrator\Service\AuthService;
use Joomla\Component\Mcpserver\Administrator\Service\CacheService;
use Joomla\Component\Mcpserver\Administrator\Service\JoomlaCache;
use Joomla\Component\Mcpserver\Administrator\Service\MonologFactory;
use Joomla\Component\Mcpserver\Administrator\Service\PolicyService;
use Joomla\Component\Mcpserver\Administrator\Service\RateLimiter;
use Joomla\Component\Mcpserver\Administrator\Service\RestClient;
use Joomla\Component\Mcpserver\Administrator\Service\RpcService;
use Joomla\Component\Mcpserver\Administrator\Service\SchemaValidator;
use Joomla\Component\Mcpserver\Administrator\Service\ToolRegistry;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use Psr\Log\LoggerInterface;

return new class implements ServiceProviderInterface {

    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactoryProvider('\\Joomla\\Component\\Mcpserver'));
        $container->registerServiceProvider(new RouterFactoryProvider('\\Joomla\\Component\\Mcpserver'));

        $container->set(Registry::class, new Registry());

        // Auth service
        $container->share(AuthService::class, function (Container $container) {
            return new AuthService(ComponentHelper::getParams('com_mcpserver'));
        });

        // Tool registry
        $container->share(ToolRegistry::class, function () {
            return new ToolRegistry();
        });

        // Schema validator
        $container->share(SchemaValidator::class, function () {
            return new SchemaValidator();
        });

        // Policy service
        $container->share(PolicyService::class, function () {
            return new PolicyService();
        });

        // Logger
        $container->share(LoggerInterface::class, function () {
            $params = ComponentHelper::getParams('com_mcpserver');
            $serverName = (string) $params->get('server_name', 'joomla-mcp-server');
            return MonologFactory::createComponentLogger('mcpserver', $serverName);
        });

        // Rate limiter
        $container->share(RateLimiter::class, function () {
            $params = ComponentHelper::getParams('com_mcpserver');
            $cacheBackend = new JoomlaCache('com_mcpserver_ratelimit');
            return new RateLimiter(
                $cacheBackend,
                (int) $params->get('rate_limit_requests', 60),
                (int) $params->get('rate_limit_window', 60)
            );
        });

        // REST client
        $container->share(RestClient::class, function (Container $container) {
            $params = ComponentHelper::getParams('com_mcpserver');
            $baseUrl = rtrim((string) $params->get('base_url', ''), '/');
            $apiToken = (string) $params->get('api_token', '');
            $verifySsl = (bool) $params->get('verify_ssl', true);

            if ($baseUrl === '') {
                $baseUrl = rtrim(Uri::root(), '/');
            }

            if ($baseUrl !== '' && !preg_match('#^https?://#i', $baseUrl)) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $baseUrl = $scheme . '://' . $host . '/' . ltrim($baseUrl, '/');
            }

            return new RestClient(
                $baseUrl,
                $apiToken ?: null,
                $container->get(LoggerInterface::class),
                $verifySsl
            );
        });

        // Cache service
        $container->share(CacheService::class, function () {
            $params = ComponentHelper::getParams('com_mcpserver');
            $cacheBackend = new JoomlaCache('com_mcpserver');
            return new CacheService($cacheBackend, (int) $params->get('cache_ttl', 60));
        });

        // RPC service
        $container->share(RpcService::class, function (Container $container) {
            $params = ComponentHelper::getParams('com_mcpserver');
            $serverName = (string) $params->get('server_name', 'joomla-mcp-server');

            return new RpcService(
                $container->get(RestClient::class),
                $container->get(CacheService::class),
                $container->get(PolicyService::class),
                $container->get(LoggerInterface::class),
                $container->get(ToolRegistry::class),
                $container->get(SchemaValidator::class),
                $serverName
            );
        });

        $container->set(
            ComponentDispatcherFactoryInterface::class,
            function (Container $container) {
                return new class ($container) implements ComponentDispatcherFactoryInterface {
                    private Container $container;

                    public function __construct(Container $container)
                    {
                        $this->container = $container;
                    }

                    public function createDispatcher(CMSApplicationInterface $application, ?Input $input = null): DispatcherInterface
                    {
                        return new Dispatcher(
                            $application,
                            $input ?? $application->getInput(),
                            $this->container->get(MVCFactoryInterface::class)
                        );
                    }
                };
            }
        );

        $container->set(
            ComponentInterface::class,
            static function (Container $container): ComponentInterface {
                return new McpserverComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class),
                    $container->get(MVCFactoryInterface::class),
                    $container->get(Registry::class),
                    $container->get(RouterFactoryInterface::class),
                    $container
                );
            }
        );
    }
};
