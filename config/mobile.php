<?php

return [
    'download_enabled' => filter_var(
        env('APP_DOWNLOAD_ENABLED', false),
        FILTER_VALIDATE_BOOL,
    ),
];
