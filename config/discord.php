<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults();

    $services
        ->autowire()
        ->autoconfigure()
        ->load('MajesticDev\\ForumifyIdCard\\Discord\\', dirname(__DIR__) . '/src/Discord/');
};
