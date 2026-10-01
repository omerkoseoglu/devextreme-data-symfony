<?php

declare(strict_types=1);

namespace DevExtreme\Data\Symfony\Tests\Functional;

use DevExtreme\Data\Symfony\Tests\Fixtures\TestKernel;
use DevExtreme\Data\Symfony\Tests\Support\Fixtures;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class FunctionalTestCase extends KernelTestCase
{
    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false, $options['maxTake'] ?? null);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function boot(array $options = []): Connection
    {
        self::bootKernel($options);

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);

        $connection->executeStatement('CREATE TABLE orders (id INTEGER PRIMARY KEY, customer TEXT, category TEXT, amount REAL, qty INTEGER, ordered_at TEXT, shipped INTEGER, note TEXT)');
        foreach (Fixtures::orders() as $row) {
            $connection->insert('orders', $row);
        }

        $connection->executeStatement('CREATE TABLE people (id INTEGER PRIMARY KEY, full_name TEXT, created_at TEXT, manager_id INTEGER)');
        $connection->insert('people', ['id' => 1, 'full_name' => 'Boss', 'created_at' => '2020-01-01 00:00:00', 'manager_id' => null]);
        $connection->insert('people', ['id' => 2, 'full_name' => 'Worker', 'created_at' => '2021-01-01 00:00:00', 'manager_id' => 1]);
        $connection->insert('people', ['id' => 3, 'full_name' => 'Intern', 'created_at' => '2022-01-01 00:00:00', 'manager_id' => 2]);

        return $connection;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    protected function get(string $uri, array $query = []): array
    {
        $query = array_map(static fn (mixed $v): mixed => is_array($v) ? json_encode($v, JSON_THROW_ON_ERROR) : $v, $query);

        $response = self::$kernel->handle(Request::create($uri, 'GET', $query), catch: true);
        self::assertInstanceOf(Response::class, $response);

        $decoded = json_decode((string) $response->getContent(), true);

        return [$response->getStatusCode(), is_array($decoded) ? $decoded : []];
    }
}
