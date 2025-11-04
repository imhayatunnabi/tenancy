<?php

declare(strict_types=1);

namespace Stancl\Tenancy\ResourceSyncing\Listeners;

use Stancl\Tenancy\Listeners\QueueableListener;
use Stancl\Tenancy\Events\TenantDeleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a tenant is deleted, clean up pivot records related to that tenant.
 * Only use this listener for cleaning up tables without tenant foreign key constraints.
 *
 * If you're using foreign key constraints on the tenant key columns in your pivot tables,
 * you still must include ->onDelete('cascade') in the constraint definition
 * for the pivot records to be deleted automatically. Without ->onDelete('cascade'),
 * the constraint will prevent tenant deletion before this listener can clean up the pivot records,
 * causing an integrity constraint violation.
 *
 * With ->onDelete('cascade'), the database will handle the cleanup automatically,
 * so there's no need to use this listener (it won't break anything, but it's redundant).
 *
 * By default, this listener only cleans up the 'tenant_resources' polymorphic pivot table,
 * and the records to delete are found by the 'tenant_id' column.
 *
 * To customize which pivot tables to clean up (or which column has the tenant key),
 * set DeleteAllTenantMappings::$pivotTables to an array of table names as the keys,
 * and their values should be tenant key column names (e.g. 'tenant_id').
 *
 * For example (e.g. in TenancyServiceProvider):
 * DeleteAllTenantMappings::$pivotTables = [
 *     'tenant_users' => 'tenant_id',
 * ];
 *
 * Tables that do not exist will be skipped.
 */
class DeleteAllTenantMappings extends QueueableListener
{
    /**
     * Pivot tables to clean up after a tenant is deleted,
     * formatted like ['table_name' => 'tenant_key_column'].
     * E.g. ['tenant_users' => 'tenant_id'].
     *
     * If empty, the listener defaults to cleaning only
     * the default pivot ('tenant_resources' with 'tenant_id' as the tenant key column).
     *
     * Set this property, e.g. in your TenancyServiceProvider,
     * for this listener to clean up specific pivot tables.
     */
    public static array $pivotTables = [];

    public function handle(TenantDeleted $event): void
    {
        $pivotTables = static::$pivotTables;

        if (! $pivotTables) {
            $pivotTables = ['tenant_resources' => 'tenant_id'];
        }

        foreach ($pivotTables as $table => $tenantKeyColumn) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where($tenantKeyColumn, $event->tenant->getTenantKey())->delete();
            }
        }
    }
}
