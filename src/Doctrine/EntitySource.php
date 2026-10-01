<?php

declare(strict_types=1);

namespace DevExtreme\Data\Symfony\Doctrine;

use DevExtreme\Data\Contracts\DataSourceInterface;
use DevExtreme\Data\LoadOptions;
use DevExtreme\Data\LoadResult;
use DevExtreme\Data\PdoSource;
use DevExtreme\Data\Sql\Dialect;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * A DevExtreme data source built from the Doctrine mapping of an entity.
 *
 * The table, the mapped fields (embeddables become nested objects, e.g. `address.city`) and the identifier come from
 * the metadata; the client sees property names, never column names. Owning many-to-one associations are exposed
 * under the association name and hold the foreign key.
 *
 * Queries run as plain SQL: results are scalar rows (no hydration, no lifecycle events), and Doctrine SQL filters
 * (soft-delete, tenancy) are NOT applied: express such constraints with `$where`.
 *
 *     EntitySource::for($entityManager, Order::class, where: 'deleted_at IS NULL');
 */
final class EntitySource implements DataSourceInterface
{
    private function __construct(private readonly PdoSource $inner)
    {
    }

    /**
     * @param class-string      $entityClass
     * @param list<string>|null $fields      Restrict to these properties (default: all mapped fields and associations)
     * @param list<mixed>       $whereParams
     *
     * @throws InvalidArgumentException for entities with inheritance mapping or unknown fields
     */
    public static function for(
        EntityManagerInterface $entityManager,
        string $entityClass,
        ?array $fields = null,
        ?string $where = null,
        array $whereParams = [],
        bool $normalizeDates = true,
    ): self {
        $metadata = $entityManager->getClassMetadata($entityClass);

        if (!$metadata->isInheritanceTypeNone()) {
            throw new InvalidArgumentException(sprintf('"%s" uses inheritance mapping, which EntitySource does not support; use DbalSource.', $entityClass));
        }

        $pdo = DbalSource::pdo($entityManager->getConnection());
        $dialect = Dialect::fromPdo($pdo);

        /** @var array<string, string> $available property => column */
        $available = [];
        foreach ($metadata->getFieldNames() as $field) {
            $available[$field] = $metadata->getColumnName($field);
        }
        foreach ($metadata->getAssociationNames() as $association) {
            if ($metadata->isSingleValuedAssociation($association)) {
                $column = $metadata->getSingleAssociationJoinColumnName($association);
                if ($column !== '' && !isset($available[$association])) {
                    $available[$association] = $column;
                }
            }
        }

        $exposed = $available;
        if ($fields !== null) {
            $exposed = [];
            foreach ($fields as $field) {
                $exposed[$field] = $available[$field]
                    ?? throw new InvalidArgumentException(sprintf('"%s" is not a mapped field of %s.', $field, $entityClass));
            }
        }

        $columns = array_map($dialect->quoteIdentifier(...), $exposed);

        $table = $dialect->quoteIdentifier($metadata->getTableName());
        $schema = $metadata->getSchemaName();
        if ($schema !== null && $schema !== '') {
            $table = $dialect->quoteIdentifier($schema) . '.' . $table;
        }

        return new self(new PdoSource(
            $pdo,
            $table,
            columns: $columns,
            primaryKey: $metadata->getIdentifierFieldNames(),
            where: $where,
            whereParams: $whereParams,
            dialect: $dialect,
            rawFrom: true,
            normalizeDates: $normalizeDates,
        ));
    }

    public function load(LoadOptions $options): LoadResult
    {
        return $this->inner->load($options);
    }
}
