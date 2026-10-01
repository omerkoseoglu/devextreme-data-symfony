# DevExtreme Data for Symfony

> **Unofficial.** This is an independent, community-maintained port. It is not affiliated with, endorsed by or supported by Developer Express Inc. "DevExtreme" and "DevExpress" are trademarks of Developer Express Inc.

Symfony bundle for `mihenk/devextreme-data`: answer DevExtreme widget requests
(`DataGrid`, `PivotGrid`, `SelectBox`, ... with `remoteOperations`) from Doctrine DBAL queries, ORM entity mappings,
tables or arrays. Filtering, sorting, paging, grouping and summaries run **in the database**.

Requires PHP 8.2+ and Symfony 7.4 or 8.x. Doctrine sources need a `pdo_sqlite`, `pdo_mysql` or `pdo_pgsql` DBAL driver.

## Install

```bash
composer require mihenk/devextreme-data-symfony
```

Symfony Flex enables the bundle; otherwise add it to `config/bundles.php`:

```php
DevExtreme\Data\Symfony\DevExtremeDataBundle::class => ['all' => true],
```

Optional `config/packages/devextreme_data.yaml`:

```yaml
devextreme_data:
    normalize_dates: true   # compare ISO-8601 filter dates as wall-clock time in SQL
    max_take: ~             # cap rows per request (null = unlimited; leave null for PivotGrid endpoints)
```

## Usage

```php
use DevExtreme\Data\Symfony\DevExtremeLoader;
use DevExtreme\Data\Symfony\Doctrine\EntitySource;

#[Route('/api/orders')]
public function orders(DevExtremeLoader $loader, EntityManagerInterface $em): JsonResponse
{
    return $loader->response(EntitySource::for($em, Order::class, where: 'deleted_at IS NULL'));
}
```

```js
const store = DevExpress.data.AspNet.createStore({ key: 'id', loadUrl: '/api/orders' });
new DevExpress.ui.dxDataGrid(el, { dataSource: store, remoteOperations: true });
```

`$loader->load($source)` returns the `LoadResult` object (JSON-serializable) instead. Parameters come from the query
string, form fields or a JSON body. Malformed requests (bad JSON, mixed `and`/`or`, unknown field, ...) throw
`BadRequestHttpException`, which Symfony renders as **HTTP 400**.

Controllers can also type-hint the parsed options:

```php
public function load(LoadOptions $options): JsonResponse { /* $options->take, ->filter, ... */ }
```

### Sources

**`EntitySource::for($em, Order::class)`** builds a source from the Doctrine mapping: table, mapped properties
(embeddables become nested objects) and identifier. The client sees *property* names, never column names; owning
many-to-one associations are exposed under the association name and hold the foreign key. Restrict what is visible
with `fields: ['id', 'total']`.

**`DbalSource::forQueryBuilder($connection, $qb, columns: [...], primaryKey: ['id'])`** accepts any DBAL query
builder, including joins, named parameters (`:name`), `IN (:ids)` arrays and `DateTimeInterface` values:

```php
$qb = $connection->createQueryBuilder()
    ->select('o.id', 'o.total', 'c.name AS customer_name')
    ->from('orders', 'o')->join('o', 'customers', 'c', 'c.id = o.customer_id')
    ->where('o.tenant_id = :tenant')->setParameter('tenant', $tenantId);

$source = DbalSource::forQueryBuilder($connection, $qb,
    columns: ['id' => 'id', 'total' => 'total', 'customer.name' => 'customer_name', 'gross' => new Raw('total * 1.2')],
    primaryKey: ['id'],
);
```

`columns` is a whitelist (client field => output column of your query, or a trusted `Raw` expression); dotted names
become nested JSON. Without it any plain column name of the query is accepted. Use it in production.

**`DbalSource::forTable($connection, 'orders', ...)`**, arrays/iterables (handled in memory) and any
`DataSourceInterface` work too.

### Things to know

- Results are scalar rows, not hydrated entities: no lifecycle events, no type conversion (dates are strings, booleans
  are 0/1 on most drivers).
- Doctrine **SQL filters** (soft-delete, tenancy) are not applied to `EntitySource`: express them with `where:`/`whereParams:`.
- `EntitySource` rejects entities with inheritance mapping (use `DbalSource`).
- All filter values are bound parameters; field names are whitelisted (mapping/`columns`) or identifier-checked.

## Development

```bash
composer install     # uses ../devextreme-php-data as a path repository
composer test
```

The tests boot a real Symfony kernel with an in-memory SQLite database and run the core package's SQL-vs-memory
parity matrix against `EntitySource`, `DbalSource::forTable` and `DbalSource::forQueryBuilder`, plus HTTP-level behaviour.

MIT licensed.
