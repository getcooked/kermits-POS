<?php

return [
    'download_enabled' => filter_var(
        env('APP_DOWNLOAD_ENABLED', true),
        FILTER_VALIDATE_BOOL,
    ),
    'release_path' => storage_path('app/releases/kermits.apk'),
];
