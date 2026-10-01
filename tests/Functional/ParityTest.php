<?php

declare(strict_types=1);

namespace DevExtreme\Data\Symfony\Tests\Functional;

use DevExtreme\Data\ArraySource;
use DevExtreme\Data\LoadOptions;
use DevExtreme\Data\Symfony\Doctrine\DbalSource;
use DevExtreme\Data\Symfony\Doctrine\EntitySource;
use DevExtreme\Data\Symfony\Tests\Fixtures\Entity\Order;
use DevExtreme\Data\Symfony\Tests\Support\Fixtures;
use DevExtreme\Data\Symfony\Tests\Support\ParityOptions;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every Doctrine source must answer exactly like the in-memory reference implementation.
 */
final class ParityTest extends FunctionalTestCase
{
    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function matrix(): iterable
    {
        yield from ParityOptions::matrix();
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('matrix')]
    public function testSources(array $options): void
    {
        $connection = $this->boot();
        $options += ['primaryKey' => ['id']];

        $expected = (new ArraySource(Fixtures::orders()))->load(LoadOptions::fromArray($options))->toArray();

        $table = DbalSource::forTable($connection, 'orders')->load(LoadOptions::fromArray($options))->toArray();
        self::assertEquals($expected, $table, 'DbalSource::forTable');

        $query = $connection->createQueryBuilder()->select('*')->from('orders', 'o')->where('o.qty >= :min')->setParameter('min', 0);
        $builder = DbalSource::forQueryBuilder($connection, $query)->load(LoadOptions::fromArray($options))->toArray();
        self::assertEquals($expected, $builder, 'DbalSource::forQueryBuilder');

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $entity = EntitySource::for($em, Order::class)->load(LoadOptions::fromArray($options))->toArray();
        self::assertEquals($expected, $entity, 'EntitySource');
    }
}
