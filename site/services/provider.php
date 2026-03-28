<?php

declare(strict_types=1);

defined('_JEXEC') or die;

$autoload = JPATH_ADMINISTRATOR . '/components/com_mcpserver/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Dispatcher\DispatcherInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\MVCFactory as MVCFactoryProvider;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\Component\Mcpserver\Site\Dispatcher\Dispatcher;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Input\Input;

return new class implements ServiceProviderInterface {
    
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactoryProvider('\\Joomla\\Component\\Mcpserver\\Site'));
        
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
                $component = new \Joomla\CMS\Extension\MVCComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class),
                    $container->get(MVCFactoryInterface::class)
                );
                
                $component->setRegistry(new \Joomla\Registry\Registry());
                
                return $component;
            }
        );
    }
};
