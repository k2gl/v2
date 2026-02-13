<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
        ->load('../src/', '../src/')
            ->exclude([
                '../src/Shared/{Exception,Services}/',
                '../src/*/{Entity,Enums}/',
                '../src/*/UseCase/*/Repository/',
                '../src/Kernel.php',
                '../src/FrankenKernel.php',
            ])
        ->load('../src/Shared/', '../src/Shared/')
            ->exclude('../src/Shared/{Exception,Services}/*')
        ->autowire()
        ->autoconfigure();

    $container->services()
        ->instanceof('App\\**\\*Controller')
            ->tag('controller.service_arguments');

    $container->services()
        ->instanceof('App\\**\\*Handler')
            ->tag('messenger.message_handler');
};
