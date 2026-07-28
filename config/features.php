<?php

return [
    'lexilingo_import' => (bool) env('FEATURE_LEXILINGO_IMPORT', false),
    'ai' => (bool) env('FEATURE_AI', false),
    'voice' => (bool) env('FEATURE_VOICE', false),
    'supervision' => (bool) env('FEATURE_SUPERVISION', false),
    'operations' => (bool) env('FEATURE_OPERATIONS', false),
];
