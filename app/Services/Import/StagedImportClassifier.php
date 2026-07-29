<?php

namespace App\Services\Import;

use App\Support\CatalogFingerprint;

class StagedImportClassifier
{
    public function classify(
        string $entity,
        array $candidate,
        ?array $existing = null,
        array $validationErrors = [],
        array $naturalKeyMatches = [],
    ): array {
        $fingerprint = CatalogFingerprint::make($entity, $candidate);
        if ($existing === null && $naturalKeyMatches !== []) {
            $validationErrors[] = count($naturalKeyMatches) > 1
                ? 'natural_key_ambiguous'
                : 'natural_key_collision';
        }

        if ($validationErrors !== []) {
            [$classification, $action] = ['invalid', 'exclude'];
        } elseif ($existing === null) {
            [$classification, $action] = ['new', 'add'];
        } elseif (($existing['source_fingerprint'] ?? null) === $fingerprint) {
            [$classification, $action] = ['exact_duplicate', 'skip'];
        } elseif (! empty($existing['local_override_at'])) {
            [$classification, $action] = ['local_conflict', 'keep_local'];
        } else {
            [$classification, $action] = ['upstream_update', 'replace'];
        }

        return [
            'normalized_fingerprint' => $fingerprint,
            'classification' => $classification,
            'selected_action' => $action,
            'target_id' => $existing['id'] ?? null,
            'base_revision' => $existing['catalog_revision'] ?? null,
            'base_fingerprint' => $existing['source_fingerprint'] ?? null,
            'base_snapshot' => $existing['source_snapshot'] ?? null,
            'base_override_at' => $existing['local_override_at'] ?? null,
            'validation_errors' => array_values(array_unique($validationErrors)),
        ];
    }
}
