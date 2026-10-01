<?php

declare(strict_types=1);

namespace DevExtreme\Data\Symfony\Tests\Functional;

final class HttpTest extends FunctionalTestCase
{
    public function testTableEndpoint(): void
    {
        $this->boot();

        [$status, $json] = $this->get('/table', [
            'skip' => 2,
            'take' => 3,
            'requireTotalCount' => 'true',
            'sort' => [['selector' => 'id', 'desc' => true]],
            'filter' => ['amount', '>', 15],
            'select' => ['id'],
        ]);

        self::assertSame(200, $status);
        self::assertSame(6, $json['totalCount']); // amounts above 15: ids 2, 3, 4, 5, 7, 8
        self::assertSame([['id' => 5], ['id' => 4], ['id' => 3]], $json['data']);
    }

    public function testGroupedSummaries(): void
    {
        $this->boot();

        [, $json] = $this->get('/entity', [
            'group' => [['selector' => 'category', 'isExpanded' => false]],
            'groupSummary' => [['selector' => 'amount', 'summaryType' => 'sum']],
            'totalSummary' => [['selector' => 'amount', 'summaryType' => 'sum']],
            'requireGroupCount' => 'true',
        ]);

        self::assertSame(3, $json['groupCount']);
        self::assertEquals([280], $json['summary']);
        self::assertSame(['Books', 'Games', 'Music'], array_column($json['data'], 'key'));
    }

    public function testQueryBuilderEndpoint(): void
    {
        $this->boot();

        [, $json] = $this->get('/dbal', ['requireTotalCount' => 'true', 'select' => ['id']]);

        // category in (Books, Games) and qty >= 2: 1, 3, 7, 8
        self::assertSame(4, $json['totalCount']);
        self::assertSame([1, 3, 7, 8], array_column($json['data'], 'id'));
    }

    public function testArrayEndpoint(): void
    {
        $this->boot();

        [, $json] = $this->get('/array', ['filter' => ['id', '>', 1], 'requireTotalCount' => 'true']);

        self::assertSame(2, $json['totalCount']);
    }

    public function testMalformedRequestsAre400(): void
    {
        $this->boot();

        foreach ([
            ['filter' => '[["a"'],
            ['take' => 'many'],
            ['filter' => [['qty', 1], 'and', ['qty', 2], 'or', ['qty', 3]]],
            ['sort' => [['selector' => 'id; DROP TABLE orders']]],
        ] as $query) {
            [$status] = $this->get('/entity', $query);
            self::assertSame(400, $status, json_encode($query));
        }

        [, $json] = $this->get('/table', ['requireTotalCount' => 'true']);
        self::assertSame(8, $json['totalCount'], 'the table is intact');
    }

    public function testControllerArgumentResolver(): void
    {
        $this->boot();

        [$status, $json] = $this->get('/resolved', ['take' => 7, 'requireTotalCount' => 'true']);

        self::assertSame(200, $status);
        self::assertSame(['take' => 7, 'total' => true], $json);
    }

    public function testMaxTakeConfiguration(): void
    {
        $this->boot(['maxTake' => 3]);

        [, $json] = $this->get('/table');
        self::assertCount(3, $json['data']);

        [, $json] = $this->get('/table', ['take' => 100]);
        self::assertCount(3, $json['data']);

        [, $json] = $this->get('/table', ['take' => 2]);
        self::assertCount(2, $json['data']);

        [, $json] = $this->get('/table', ['isCountQuery' => 'true']);
        self::assertSame(8, $json['totalCount']);
    }

    public function testInvalidMaxTakeConfigurationIsRejected(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        (new \DevExtreme\Data\Symfony\Tests\Fixtures\TestKernel('test', false, 0))->boot();
    }
}
