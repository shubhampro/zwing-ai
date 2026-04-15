# Dynamic database connections

This application stores **remote database connection definitions in the database** (not in `.env`). Each row becomes a Laravel connection at runtime. **Read** connections can enforce a SQL-level guard against accidental writes; **write** connections are opt-in per row.

## Requirements

- Run migrations: `php artisan migrate`
- **MongoDB** remote connections require the PHP `mongodb` extension and the `mongodb/laravel-mongodb` Composer package (already declared in `composer.json`).
- After pulling changes or editing PHP routes/controllers that affect the frontend, regenerate Wayfinder artifacts locally (they are gitignored):

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

## Configuration

- `config/dynamic_database.php` — global SQL read-only guard toggle: `DYNAMIC_DB_READ_ONLY_GUARD_ENABLED` (default `true`).
- `config/database.php` — static `remote_*` connection blocks were removed; dynamic connections are merged at boot from the `database_connections` table.

## Schema: `database_connections`

| Column | Purpose |
|--------|---------|
| `slug` | Laravel connection name (unique), e.g. `warehouse_read`. Use letters, numbers, hyphen, underscore. |
| `connection_group` | Groups a **read** row and a **write** row for the same logical system, e.g. `warehouse`. |
| `driver` | `mysql`, `pgsql`, or `mongodb`. |
| `access_mode` | `read` or `write`. |
| `writes_enabled` | For **write** rows: must be `true` before `writeForGroup()` is allowed (default `false`). |
| `enforce_read_only_sql_guard` | For **read** rows on MySQL/PostgreSQL: when `true`, mutating SQL throws (default `true`). |
| `is_active` | Inactive rows are not registered as connections. |
| Credentials & options | `host`, `port`, `database`, `username`, `password` (encrypted), URL/SSL/Mongo fields, optional JSON `ssh_tunnel` (metadata only), `extra_options` (`pdo` / `mongo` keys for advanced overrides). |

Passwords are **encrypted at rest** and appear as `[REDACTED]` in activity log snapshots.

## Schema: `database_connection_logs`

Append-only audit rows for **created** and **updated** events: `user_id`, `action`, `connection_slug`, `connection_group`, `before`, `after` (JSON), `ip_address`, `user_agent`, `created_at`.

## Runtime registration

- `App\Services\DatabaseConnectionRegistrar::register()` loads active rows, merges `config('database.connections.{slug}')`, purges stale connections, and sets `dynamic_database.read_only_guarded_slugs` for the SQL guard.
- Registered on `AppServiceProvider::boot` and after each `DatabaseConnection` **save** / **delete** via `DatabaseConnectionObserver`.

## SQL read-only guard

- `App\Support\Database\GuardsReadOnlySqlRemoteConnections` listens to queries; on guarded connection names it blocks statements detected as writes (MySQL/PostgreSQL only).
- MongoDB relies on separate read/write connection config (e.g. read preference) and using the correct `slug`.

## Resolving connections in code

```php
use App\Support\Database\ResolvesRemoteWriteConnection;
use Illuminate\Support\Facades\DB;

// By group (read / write rows must exist and match access_mode)
$read = ResolvesRemoteWriteConnection::readForGroup('warehouse');
$write = ResolvesRemoteWriteConnection::writeForGroup('warehouse'); // requires writes_enabled on the write row

// Or by slug
DB::connection('warehouse_read')->select('...');
```

## Web UI

Requires **authenticated** and **verified** users (same middleware as `dashboard`).

| Page | Route name | Purpose |
|------|------------|---------|
| List | `database-connections.index` | Table of connections; links to add, edit, logs. |
| Create | `database-connections.create` | Add connection form. |
| Edit | `database-connections.edit` | Update connection; password optional. |
| Activity logs | `database-connections.activity-logs` | Paginated create/update audit trail. |

Navigation: sidebar item **Connections**.

Authorization is via `DatabaseConnectionPolicy` (currently allows all authenticated users for `viewAny`, `create`, `update`, and `viewActivityLogs`; `delete` is disabled).

## Tests

- Feature: `tests/Feature/DatabaseConnectionManagementTest.php` (UI flows and logs).
- Feature: `tests/Feature/DynamicDatabaseConnectionRegistrationTest.php` (config registration).
- Unit: `tests/Unit/DatabaseConnectionRegistrarConfigTest.php`, `ResolvesRemoteWriteConnectionTest.php`, `WriteIntentSqlDetectorTest.php`.

Feature tests use `RefreshDatabase` (see `tests/Pest.php`).

## Related classes (quick reference)

- `App\Models\DatabaseConnection` / `DatabaseConnectionLog`
- `App\Http\Controllers\DatabaseConnectionController`
- `App\Http\Requests\StoreDatabaseConnectionRequest` / `UpdateDatabaseConnectionRequest`
- `App\Services\DatabaseConnectionChangeLogger`
- `App\Exceptions\UnresolvedDynamicDatabaseConnectionException` / `WriteOperationOnReadOnlyRemoteConnectionException`
