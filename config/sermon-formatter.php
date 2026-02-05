<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Claude API Configuration
    |--------------------------------------------------------------------------
    */

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('SERMON_FORMATTER_MODEL', 'claude-sonnet-4-5-20250929'),
        'max_tokens' => (int) env('SERMON_FORMATTER_MAX_TOKENS', 8192),
        'api_version' => '2023-06-01',
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Configuration
    |--------------------------------------------------------------------------
    */

    'processing' => [
        // The Bard field handle to write formatted content into
        'target_field' => env('SERMON_FORMATTER_TARGET_FIELD', 'notes'),

        // Collections that contain sermon entries
        'collections' => ['messages', 'nss_messages'],

        // Maximum file size in MB
        'max_file_size' => 10,

        // Allowed file extensions
        'allowed_extensions' => ['docx', 'rtf'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'name' => env('SERMON_FORMATTER_QUEUE_NAME', 'default'),
        'retries' => 3,
        'retry_after' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    'rate_limit' => [
        // Maximum concurrent Claude API requests
        'max_concurrent' => (int) env('SERMON_FORMATTER_RATE_LIMIT', 1),

        // Minimum seconds between API requests
        'cooldown_seconds' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => true,
        'level' => env('SERMON_FORMATTER_LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LibreOffice Path (for RTF conversion fallback)
    |--------------------------------------------------------------------------
    */

    'libreoffice_path' => env('LIBREOFFICE_PATH', '/usr/bin/libreoffice'),

];
