<?php

namespace App\Models\Concerns;

use App\Support\CatalogFingerprint;
use Illuminate\Support\Facades\DB;

trait HasCatalogOwnership
{
    /**
     * @return array{0: static, 1: bool}
     */
    public static function syncFromSource(
        string $entity,
        string $sourceSystem,
        string $externalId,
        array $attributes,
    ): array {
        return DB::transaction(function () use ($entity, $sourceSystem, $externalId, $attributes): array {
            $fingerprint = CatalogFingerprint::make($entity, $attributes);
            $model = static::query()
                ->where('source_system', $sourceSystem)
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();

            if ($model?->local_override_at || $model?->source_fingerprint === $fingerprint) {
                return [$model, false];
            }

            $model ??= new static;
            $model->fill($attributes);
            $model->forceFill([
                'source_system' => $sourceSystem,
                'external_id' => $externalId,
                'source_fingerprint' => $fingerprint,
                'source_snapshot' => $attributes,
                'last_synced_at' => now(),
                'catalog_revision' => (int) $model->catalog_revision + 1,
            ])->save();

            return [$model, true];
        });
    }

    public function applyLocalEdit(array $attributes): bool
    {
        $updated = DB::transaction(function () use ($attributes) {
            $locked = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $locked->fill($attributes)->forceFill([
                'local_override_at' => now(),
                'catalog_revision' => (int) $locked->catalog_revision + 1,
            ])->save();

            return $locked;
        });

        $this->setRawAttributes($updated->getAttributes(), true);

        return true;
    }
}
