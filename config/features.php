<?php

return [
    'lexilingo_import' => (bool) env('FEATURE_LEXILINGO_IMPORT', false),
    // Separate from the fetch flag above: staging rows for review must never
    // imply permission to write them into the catalog. Stays false until the
    // release evidence in docs/release-evidence/ is recorded.
    'lexilingo_import_apply' => (bool) env('FEATURE_LEXILINGO_IMPORT_APPLY', false),
    // Both upload and apply behind one flag to start: no partial rollout where
    // staging is safe to expose separately, unlike the LexiLingo pair above.
    'file_catalog_import' => (bool) env('FEATURE_FILE_CATALOG_IMPORT', false),
    'ai' => (bool) env('FEATURE_AI', false),
    'voice' => (bool) env('FEATURE_VOICE', false),
    'youtube_listening' => (bool) env('FEATURE_YOUTUBE_LISTENING', false),
    'supervision' => (bool) env('FEATURE_SUPERVISION', false),
    'operations' => (bool) env('FEATURE_OPERATIONS', false),
];
