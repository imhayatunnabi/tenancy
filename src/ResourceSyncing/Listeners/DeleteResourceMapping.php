<?php

declare(strict_types=1);

namespace Stancl\Tenancy\ResourceSyncing\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Listeners\QueueableListener;
use Stancl\Tenancy\ResourceSyncing\Events\SyncedResourceDeleted;
use Stancl\Tenancy\ResourceSyncing\Syncable;
use Stancl\Tenancy\ResourceSyncing\SyncMaster;

class DeleteResourceMapping extends QueueableListener
{
    public static bool $shouldQueue = false;

    public function handle(SyncedResourceDeleted $event): void
    {
        $centralResource = $this->getCentralResource($event->model);

        if (! $centralResource) {
            return;
        }

        // Delete pivot records if the central resource doesn't use soft deletes
        // or the central resource was deleted using forceDelete()
        if ($event->forceDelete || ! in_array(SoftDeletes::class, class_uses_recursive($centralResource::class), true)) {
            Pivot::withoutEvents(function () use ($centralResource, $event) {
                $centralResource?->tenants()->detach($event->tenant);
            });
        }
    }

    public function getCentralResource(Syncable&Model $resource): SyncMaster|null
    {
        if ($resource instanceof SyncMaster) {
            return $resource;
        }

        $centralResourceClass = $resource->getCentralModelName();

        /** @var (SyncMaster&Model)|null $centralResource */
        $centralResource = $centralResourceClass::firstWhere(
            $resource->getGlobalIdentifierKeyName(),
            $resource->getGlobalIdentifierKey()
        );

        return $centralResource;
    }
}
