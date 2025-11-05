<?php

declare(strict_types=1);

namespace Stancl\Tenancy\ResourceSyncing\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Events\TenantDeleted;
use Stancl\Tenancy\Listeners\QueueableListener;

/**
 * When a tenant is deleted, clean up pivot records related to that tenant
 * in the pivot tables specified in the $pivotTables property (see the property for details).
 *
 * Only use this listener for cleaning up tables without tenant foreign key constraints.
 * When using foreign key constraints, you'll have to use ->onDelete('cascade')
 * on the constraint definition for the cleanup instead of utilizing this listener because
 * the constraint will prevent tenant deletion before this listener can clean up the pivot records,
 * causing an integrity constraint violation.
 */
class DeleteAllTenantMappings extends QueueableListener
{
    /**
     * Pivot tables to clean up after a tenant is deleted,
     * formatted like ['table_name' => 'tenant_key_column'].
     *
     * By default, the listener only cleans up the default pivot
     * ('tenant_resources' with 'tenant_id' as the tenant key column).
     *
     * To customize this, set this property, e.g. in TenancyServiceProvider:
     * DeleteAllTenantMappings::$pivotTables = [
     *     'tenant_users' => 'tenant_id',
     * ];
     *
     * Non-existent tables specified in the property will be skipped.
     */
    public static array $pivotTables = ['tenant_resources' => 'tenant_id'];

    public function handle(TenantDeleted $event): void
    {
        foreach (static::$pivotTables as $table => $tenantKeyColumn) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where($tenantKeyColumn, $event->tenant->getTenantKey())->delete();
            }
        }
    }
}
