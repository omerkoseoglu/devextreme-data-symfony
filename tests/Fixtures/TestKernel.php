<?php

declare(strict_types=1);

namespace DevExtreme\Data\Symfony\Tests\Fixtures;

use DevExtreme\Data\Symfony\DevExtremeDataBundle;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(string $environment = 'test', bool $debug = true, private readonly ?int $maxTake = null)
    {
        parent::__construct($environment . ($maxTake === null ? '' : '_cap' . $maxTake), $debug);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DevExtremeDataBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);

        $container->extension('devextreme_data', $this->maxTake === null ? [] : ['max_take' => $this->maxTake]);

        $services = $container->services();
        $services->defaults()->autowire()->autoconfigure();
        $services->load('DevExtreme\\Data\\Symfony\\Tests\\Fixtures\\Controller\\', __DIR__ . '/Controller/');

        $services->set(Connection::class)
            ->factory([DriverManager::class, 'getConnection'])
            ->args([['driver' => 'pdo_sqlite', 'memory' => true]])
            ->public();
        $services->set(EntityManagerInterface::class)
            ->factory([self::class, 'createEntityManager'])
            ->args([service(Connection::class)])
            ->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/Controller/', 'attribute');
    }

    public static function createEntityManager(Connection $connection): EntityManagerInterface
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/Entity'], true);
        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        return new EntityManager($connection, $config);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/devextreme_symfony_test/' . self::VERSION . '/' . $this->environment . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/devextreme_symfony_test/' . self::VERSION . '/' . $this->environment . '/log';
    }
}
