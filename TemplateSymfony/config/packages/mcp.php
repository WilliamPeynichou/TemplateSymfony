<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('mcp', [
        'app' => 'TemplateSymfony',
        'version' => '0.1.0',
        'client_transports' => [
            'stdio' => true,
            'http' => false,
        ],
    ]);
};
