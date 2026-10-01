<?php

declare(strict_types=1);

namespace DevExtreme\Data\Symfony\Doctrine;

use DevExtreme\Data\Contracts\DataSourceInterface;
use DevExtreme\Data\LoadOptions;
use DevExtreme\Data\LoadResult;
use DevExtreme\Data\PdoSource;
use DevExtreme\Data\Sql\Dialect;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use LogicException;
use PDO;

/**
 * A DevExtreme data source over a Doctrine DBAL query builder or table.
 *
 * The query is compiled to SQL (named and array parameters are converted to positional ones) and used as a derived
 * table; filtering, sorting, paging, grouping and summaries then run in the database on top of it.
 * Only `pdo_*` DBAL drivers (pdo_sqlite, pdo_mysql, pdo_pgsql) are supported.
 *
 *     DbalSource::forQueryBuilder(
 *         $connection,
 *         $connection->createQueryBuilder()
 *             ->select('o.id', 'o.total', 'c.name AS customer_name')
 *             ->from('orders', 'o')->join('o', 'customers', 'c', 'c.id = o.customer_id')
 *             ->where('o.tenant_id = :tenant')->setParameter('tenant', 7),
 *         columns: ['id' => 'id', 'total' => 'total', 'customer.name' => 'customer_name'],
 *         primaryKey: ['id'],
 *     );
 */
final class DbalSource implements DataSourceInterface
{
    private function __construct(private readonly PdoSource $inner)
    {
    }

    /**
     * @param array<string, string|Raw>|null $columns    Whitelist: client field => output column of the query (or a {@see Raw}
     *                                                   expression). Null accepts any plain column name of the query.
     * @param list<string>                   $primaryKey stable-sort tie-breaker (and key for paginateViaPrimaryKey)
     */
    public static function forQueryBuilder(
        Connection $connection,
        QueryBuilder $query,
        ?array $columns = null,
        array $primaryKey = [],
        bool $normalizeDates = true,
    ): self {
        $pdo = self::pdo($connection);
        $dialect = Dialect::fromPdo($pdo);

        [$sql, $params] = SqlParameters::positional($query->getSQL(), $query->getParameters());

        return new self(new PdoSource(
            $pdo,
            sprintf('(%s) AS %s', $sql, $dialect->quoteIdentifier('devextreme_source')),
            columns: self::columnMap($columns, $dialect),
            primaryKey: $primaryKey,
            dialect: $dialect,
            rawFrom: true,
            normalizeDates: $normalizeDates,
            fromParams: $params,
        ));
    }

    /**
     * A table or view (optionally schema-qualified).
     *
     * @param array<string, string|Raw>|null $columns
     * @param list<string>                   $primaryKey
     * @param list<mixed>                    $whereParams
     */
    public static function forTable(
        Connection $connection,
        string $table,
        ?array $columns = null,
        array $primaryKey = [],
        ?string $where = null,
        array $whereParams = [],
        bool $normalizeDates = true,
    ): self {
        $pdo = self::pdo($connection);

        return new self(new PdoSource(
            $pdo,
            $table,
            columns: self::columnMap($columns, Dialect::fromPdo($pdo)),
            primaryKey: $primaryKey,
            where: $where,
            whereParams: $whereParams,
            normalizeDates: $normalizeDates,
        ));
    }

    public function load(LoadOptions $options): LoadResult
    {
        return $this->inner->load($options);
    }

    /**
     * @internal
     */
    public static function pdo(Connection $connection): PDO
    {
        $native = $connection->getNativeConnection();

        if (!$native instanceof PDO) {
            throw new LogicException('DevExtreme sources need a pdo_* DBAL driver (pdo_sqlite, pdo_mysql, pdo_pgsql).');
        }

        return $native;
    }

    /**
     * @param array<string, string|Raw>|null $columns
     *
     * @return array<string, string>|null
     */
    private static function columnMap(?array $columns, Dialect $dialect): ?array
    {
        if ($columns === null) {
            return null;
        }

        $map = [];
        foreach ($columns as $field => $column) {
            $map[$field] = $column instanceof Raw ? $column->sql : $dialect->quoteIdentifier($column);
        }

        return $map;
    }
}
