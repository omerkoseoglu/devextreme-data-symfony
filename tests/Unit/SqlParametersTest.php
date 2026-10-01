<?php

declare(strict_types=1);

namespace DevExtreme\Data\Symfony\Tests\Unit;

use DevExtreme\Data\Symfony\Doctrine\SqlParameters;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SqlParametersTest extends TestCase
{
    public function testNoParameters(): void
    {
        self::assertSame(['SELECT 1', []], SqlParameters::positional('SELECT 1', []));
    }

    public function testPositionalParametersAreOrderedByKey(): void
    {
        self::assertSame(['a = ? AND b = ?', [1, 2]], SqlParameters::positional('a = ? AND b = ?', [1 => 2, 0 => 1]));
    }

    public function testNamedParametersFollowTheirOrderInTheSql(): void
    {
        [$sql, $values] = SqlParameters::positional('b = :b AND a = :a AND b2 = :b', ['a' => 1, 'b' => 2]);

        self::assertSame('b = ? AND a = ? AND b2 = ?', $sql);
        self::assertSame([2, 1, 2], $values);
    }

    public function testArraysExpandToPlaceholderLists(): void
    {
        [$sql, $values] = SqlParameters::positional('id IN (:ids) AND x = :x', ['ids' => [1, 2, 3], 'x' => 'y']);

        self::assertSame('id IN (?, ?, ?) AND x = ?', $sql);
        self::assertSame([1, 2, 3, 'y'], $values);
    }

    public function testLiteralsIdentifiersAndCastsAreLeftAlone(): void
    {
        [$sql, $values] = SqlParameters::positional(
            "SELECT ':not_a_param', \":also_not\", `:nor_this`, x::text FROM t WHERE y = :y",
            ['y' => 5],
        );

        self::assertSame("SELECT ':not_a_param', \":also_not\", `:nor_this`, x::text FROM t WHERE y = ?", $sql);
        self::assertSame([5], $values);
    }

    public function testDateTimesAreFormatted(): void
    {
        [, $values] = SqlParameters::positional(':d', ['d' => new \DateTimeImmutable('2024-05-06 07:08:09')]);

        self::assertSame(['2024-05-06 07:08:09'], $values);
    }

    public function testLeadingColonKeysAreAccepted(): void
    {
        self::assertSame(['a = ?', [1]], SqlParameters::positional('a = :a', [':a' => 1]));
    }

    /**
     * @return iterable<string, array{0: string, 1: array<int|string, mixed>}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'missing named value' => ['a = :a AND b = :b', ['a' => 1]];
        yield 'empty array' => ['a IN (:a)', ['a' => []]];
        yield 'array with positional' => ['a IN (?)', [[1, 2]]];
        yield 'object value' => ['a = :a', ['a' => new \stdClass()]];
    }

    /**
     * @param array<int|string, mixed> $params
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidProvider')]
    public function testInvalidInput(string $sql, array $params): void
    {
        $this->expectException(InvalidArgumentException::class);

        SqlParameters::positional($sql, $params);
    }
}
