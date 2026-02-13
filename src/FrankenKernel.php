<?php

declare(strict_types=1);

namespace App;

if (\defined('FRANKEN_KERNEL_LOADED')) {
    return;
}
define('FRANKEN_KERNEL_LOADED', true);

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class FrankenKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    public function registerBundles(): iterable
    {
        $contents = require $this->getProjectDir() . '/config/bundles.php';
        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import('../config/{packages}/*.yaml');
        $container->import('../config/{packages}/'.$this->environment.'/*.yaml');

        if (\is_file($confPath = $this->getProjectDir().'/config/services.yaml')) {
            $container->import($confPath);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // Load attribute-based routes from src directory
        $routes->import('../src/', 'attribute');
    }
}
