<?php

declare(strict_types=1);

namespace DevExtreme\Data\Symfony\Tests\Functional;

use DevExtreme\Data\LoadOptions;
use DevExtreme\Data\Symfony\Doctrine\DbalSource;
use DevExtreme\Data\Symfony\Doctrine\EntitySource;
use DevExtreme\Data\Symfony\Doctrine\Raw;
use DevExtreme\Data\Symfony\Tests\Fixtures\Entity\Order;
use DevExtreme\Data\Symfony\Tests\Fixtures\Entity\Person;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

final class DoctrineSourcesTest extends FunctionalTestCase
{
    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function load(\DevExtreme\Data\Contracts\DataSourceInterface $source, array $options = []): array
    {
        return $source->load(LoadOptions::fromArray($options))->toArray();
    }

    private function em(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testQueryBuilderNamedAndArrayParametersAreBound(): void
    {
        $connection = $this->boot();
        $query = $connection->createQueryBuilder()
            ->select('id', 'category', 'qty')
            ->from('orders')
            ->where('category IN (:categories)')
            ->andWhere('qty >= :qty')
            ->setParameter('categories', ['Books', 'Games'])
            ->setParameter('qty', 3);

        $result = $this->load(DbalSource::forQueryBuilder($connection, $query, primaryKey: ['id']), ['select' => ['id']]);

        // Books: 7 (4); Games: 3 (3), 8 (6)
        self::assertSame([['id' => 3], ['id' => 7], ['id' => 8]], $result['data']);
    }

    public function testQueryBuilderPositionalParametersAndDateTimes(): void
    {
        $connection = $this->boot();
        $query = $connection->createQueryBuilder()
            ->select('id')
            ->from('orders')
            ->where('ordered_at >= ?')
            ->andWhere('shipped = ?')
            ->setParameter(0, new \DateTimeImmutable('2025-01-01 00:00:00'))
            ->setParameter(1, 1);

        $result = $this->load(DbalSource::forQueryBuilder($connection, $query, primaryKey: ['id']));

        self::assertSame([6, 8], array_column($result['data'], 'id'));
    }

    public function testQueryBuilderWithJoinColumnsAndRawExpressions(): void
    {
        $connection = $this->boot();
        $query = $connection->createQueryBuilder()
            ->select('o.id', 'o.amount', 'o.qty', 'p.full_name AS who')
            ->from('orders', 'o')
            ->join('o', 'people', 'p', 'p.id = (o.id % 3) + 1');

        $source = DbalSource::forQueryBuilder(
            $connection,
            $query,
            columns: ['id' => 'id', 'who.name' => 'who', 'total' => new Raw('amount * qty')],
            primaryKey: ['id'],
        );

        $result = $this->load($source, ['filter' => [['total', '>=', 240], 'and', ['who.name', 'Boss']], 'select' => ['id', 'who.name']]);

        // total >= 240 matches orders 5, 7, 8; "Boss" owns only orders 3 and 6
        self::assertSame([], $result['data']);

        $any = $this->load($source, ['filter' => ['total', '>=', 240], 'select' => ['id', 'who.name'], 'sort' => [['selector' => 'id']]]);
        self::assertSame(
            [['id' => 5, 'who' => ['name' => 'Intern']], ['id' => 7, 'who' => ['name' => 'Worker']], ['id' => 8, 'who' => ['name' => 'Intern']]],
            $any['data'],
        );
    }

    public function testWhitelistRejectsUnknownFields(): void
    {
        $connection = $this->boot();
        $source = DbalSource::forTable($connection, 'orders', columns: ['id' => 'id'], primaryKey: ['id']);

        $this->expectException(InvalidArgumentException::class);
        $this->load($source, ['filter' => ['amount', '>', 1]]);
    }

    public function testEntitySourceMapsPropertiesToColumnsAndAssociations(): void
    {
        $this->boot();
        $source = EntitySource::for($this->em(), Person::class);

        $result = $this->load($source, ['sort' => [['selector' => 'id']]]);

        // properties, not columns; the owning many-to-one is exposed under its name and holds the foreign key
        self::assertSame(
            [
                ['id' => 1, 'fullName' => 'Boss', 'createdAt' => '2020-01-01 00:00:00', 'manager' => null],
                ['id' => 2, 'fullName' => 'Worker', 'createdAt' => '2021-01-01 00:00:00', 'manager' => 1],
                ['id' => 3, 'fullName' => 'Intern', 'createdAt' => '2022-01-01 00:00:00', 'manager' => 2],
            ],
            $result['data'],
        );

        self::assertSame([['id' => 3]], $this->load($source, ['filter' => ['manager', 2], 'select' => ['id']])['data']);
        self::assertSame([['id' => 2]], $this->load($source, ['filter' => ['fullName', 'startswith', 'wor'], 'select' => ['id']])['data']);

        $grouped = $this->load($source, ['group' => [['selector' => 'manager', 'isExpanded' => false]]]);
        self::assertSame([null, 1, 2], array_column($grouped['data'], 'key'));
    }

    public function testEntitySourceFieldsAndWhere(): void
    {
        $this->boot();

        $source = EntitySource::for($this->em(), Order::class, fields: ['id', 'category'], where: 'shipped = ?', whereParams: [1]);

        $result = $this->load($source, ['requireTotalCount' => true, 'take' => 1]);
        self::assertSame(5, $result['totalCount']);
        self::assertSame([['id' => 1, 'category' => 'Books']], $result['data']);

        $this->expectException(InvalidArgumentException::class);
        $this->load($source, ['filter' => ['amount', '>', 0]]); // not exposed
    }

    public function testEntitySourceRejectsUnknownFields(): void
    {
        $this->boot();

        $this->expectException(InvalidArgumentException::class);
        EntitySource::for($this->em(), Order::class, fields: ['id', 'nope']);
    }
}
