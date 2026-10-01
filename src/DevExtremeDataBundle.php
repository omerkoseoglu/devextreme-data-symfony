<?php

declare(strict_types=1);

namespace DevExtreme\Data\Symfony;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class DevExtremeDataBundle extends AbstractBundle
{
    protected string $extensionAlias = 'devextreme_data';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('normalize_dates')
                    ->info('Compare ISO-8601 filter dates as wall-clock time (no timezone conversion) in SQL.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('max_take')
                    ->info('Upper bound for "take"; null = unlimited. Leave null for endpoints that feed a PivotGrid.')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn (mixed $v): bool => $v !== null && (!is_int($v) || $v < 1))
                        ->thenInvalid('max_take must be null or a positive integer.')
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{normalize_dates: bool, max_take: int|null} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set('devextreme_data.loader', DevExtremeLoader::class)
            ->args([service('request_stack'), $config['normalize_dates'], $config['max_take']])
            ->public();
        $services->alias(DevExtremeLoader::class, 'devextreme_data.loader')->public();

        $services->set('devextreme_data.load_options_resolver', LoadOptionsValueResolver::class)
            ->args([service('devextreme_data.loader')])
            ->tag('controller.argument_value_resolver', ['priority' => 50]);
    }
}
