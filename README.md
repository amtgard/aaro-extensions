# aaro-extensions

Extended production features for the [AARO ORM](https://github.com/amtgard/active-record-orm) (`amtgard/active-record-orm`).

## Requirements

- PHP 8.3+
- Composer
- For integration tests: Docker (MariaDB via Compose)

## Installation

```bash
composer require amtgard/aaro-extensions
```

Local development against the sibling ORM checkout:

```bash
composer install
```

The package `composer.json` includes a path repository to `../active-record-orm`.

---

## Usage

Wrap a core table (or repository) so inserts, updates, and deletes are recorded automatically in a companion audit table. Application code continues to use the core table normally.

### Table level

```php
use Amtgard\AaroExtensions\Audit\AuditConfiguration;
use Amtgard\AaroExtensions\Audit\AuditTableFactory;

$config = AuditConfiguration::builder()
    ->editedBySupplier(fn () => $currentUserId)
    ->build();

$table = AuditTableFactory::build($database, $policy, 'users', $config);

$table->clear();
$table->name = 'Alice';
$table->save();
```

### Repository / EntityManager level

```php
use Amtgard\AaroExtensions\Audit\AuditTableFactory;

$em = EntityManager::builder()
    ->database($database)
    ->dataAccessPolicy($policy)
    ->mapperSupplier(fn ($db, $policy, $name) => AuditTableFactory::mapperSupplier(
        $db,
        $policy,
        $name,
        $auditConfiguration,
    ))
    ->build();
```

Or use **`AuditRepositoryEntityTrait`** on a `RepositoryEntity` and implement `auditConfiguration()`.

### Configuration

```php
AuditConfiguration::builder()
    ->auditTableName('users_history')   // default: {table}_audit
    ->editedBySupplier(fn () => 42)     // or EditedBySupplier instance
    ->auditInserts(false)               // default: true
    ->build();
```

| Option | Default | Description |
|---|---|---|
| Audit table name | `{core_table}_audit` | Companion audit table |
| `editedBySupplier` | `null` | Resolves `edited_by_id` on each audit row |
| `auditInserts` | `true` | Write an audit row when a core row is inserted |

---

## Keeping audit tables in sync

Every auditable core table needs a matching audit table. By convention the audit table is named `{core_table}_audit` (configurable via `AuditConfiguration`).

When the **core schema changes**, the audit table must be updated as well:

| Core change | Audit table action |
|---|---|
| New table | Create `{table}_audit` with metadata columns plus nullable mirrors of every core column except `id` |
| Column added | Add the same column to the audit table (`null => true`) |
| Column type changed | Update the mirrored column type on the audit table |
| Column dropped | Rename the audit column to `dropped_{n}_{column_name}` to preserve historical values |

The audit table always includes these metadata columns (Phinx adds an auto-increment `id` PK automatically):

- `audit_id` — core row `id` (NOT NULL)
- `edit_at`, `edit_fields`, `edited_by_id`, `operation`

See [Audit reference](#audit-reference) below for full schema and write semantics.

### Migration workflow

1. **Create** — after defining a core table, create its audit table in Phinx with mirrored columns (all nullable except metadata).
2. **Patch** — whenever a core migration adds, changes, or drops columns, apply the corresponding change to the audit table in the same release (use `dropped_{n}_*` for removed columns).

Migration generator and patcher CLI commands are planned for Phase 3:

```bash
# Generate Phinx migration(s) for audit table(s) from the live database schema
composer audit:phinx -- --env=.env [--table=users] --out-dir=db/migrations

# Emit a patch migration after core schema changes
composer audit:patch -- --env=.env [--table=users] --out-dir=db/migrations
```

Until those commands ship, maintain audit migrations manually alongside your core table migrations.

### Example audit migration (Phinx)

```php
$this->table('users_audit')
    ->addColumn('audit_id', 'integer', ['null' => false])
    ->addColumn('edit_at', 'datetime', ['null' => false])
    ->addColumn('edit_fields', 'json', ['null' => true])
    ->addColumn('edited_by_id', 'integer', ['null' => true])
    ->addColumn('operation', 'enum', ['values' => ['insert', 'update', 'delete'], 'null' => false])
    ->addColumn('name', 'string', ['null' => true, 'limit' => 255])
    ->addColumn('email', 'string', ['null' => true, 'limit' => 255])
    ->create();
```

---

## Testing

### Unit tests

```bash
composer test:unit
```

### Integration tests (Docker)

Integration tests use MariaDB on port **24307** via this package's `docker-compose.dev.yml`.

```bash
composer docker:up
composer migrate:test
composer test:integ
composer docker:down
```

Copy `test-resources/.env.example` to `test-resources/.env` if needed.

```bash
composer test    # unit + integration
```

---

## Audit reference

Technical details for the row-level audit feature.

### Overview

Transparent auditing for ORM `Table` and `Repository` usage. The wrapper records inserts, updates, and deletes in a companion audit table without audit-specific code in business logic.

Each audit row is a **rolling snapshot** of what happened (new/current values), ordered by time so you can answer “who changed this record to what?” and replay a record’s history from insert through delete.

### Table pairing

| Core table | Audit table (default) |
|---|---|
| `users` | `users_audit` |

### Schema

Phinx adds an auto-increment **`id`** as the audit row primary key. You declare the rest.

**Metadata columns**

| Column | Type | Nullable | Description |
|---|---|---|---|
| `audit_id` | same as core PK | NO | Core row `id` this event belongs to |
| `edit_at` | datetime | NO | When the event was recorded |
| `edit_fields` | JSON | YES | Field names changed on **update**; `[]` on insert/delete |
| `edited_by_id` | int (typical) | YES | Actor id from supplier |
| `operation` | enum | NO | `insert`, `update`, or `delete` |

**Mirrored core columns**

- Every core column **except** `id` is replicated on the audit table.
- All mirrored columns are **explicitly nullable**.
- Values are snapshots after the operation (see write semantics).

When a core column is dropped, patch migrations preserve history as `dropped_{n}_{column_name}` on the audit table.

### Write semantics

| Event | When | `operation` | `edit_fields` | Mirrored columns |
|---|---|---|---|---|
| Insert | After core insert | `insert` | `[]` | All inserted values |
| Update | After core update (only if something changed) | `update` | Changed field names | New values for changed fields only |
| Delete | Before core delete | `delete` | `[]` | Full final row |

Example for core row `id = 42`:

```
id | audit_id | operation | edit_at          | edit_fields      | string_value
---+----------+-----------+------------------+------------------+-------------
 1 |       42 | insert    | 2026-06-07 10:00 | []               | Alice
 2 |       42 | update    | 2026-06-07 11:00 | ["string_value"] | Alicia
 3 |       42 | delete    | 2026-06-07 12:00 | []               | Alicia
```

### Package layout

```
src/Audit/
  AuditTable.php              Wrapper around core Table
  AuditTableFactory.php       Builds AuditTable + EntityMapper supplier
  AuditConfiguration.php      Table name, supplier, insert toggle
  AuditSnapshotCapture.php    Computes snapshot payload per operation
  AuditSnapshot.php           Value object for one audit event
  AuditOperation.php          insert | update | delete
  AuditColumns.php            Metadata column name constants
  AuditRepository.php         Repository base class
  AuditRepositoryEntityTrait.php
  EditedBySupplier.php        Optional supplier interface
```

---

## Roadmap

- [x] **Phase 1** — Runtime audit wrapper + unit tests
- [x] **Phase 2** — Docker Compose + integration tests
- [ ] **Phase 3** — Phinx migration generator and audit-table patcher (`audit:phinx`, `audit:patch`)

---

## License

MIT — see [LICENSE](LICENSE).
