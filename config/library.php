<?php

return [
    'cors_origin' => env('CORS_ORIGIN', 'http://localhost:5173'),
    // HS256 needs at least 32 bytes.
    'jwt_secret' => env('JWT_SECRET'),
    'jwt_ttl_seconds' => 28800,
    // If unset or not writable, the cover adapter falls back to storage/covers.
    'covers_dir' => env('COVERS_DIR') ?: storage_path('covers'),
    'clock_offset_days' => (int) env('CLOCK_OFFSET_DAYS', 0),
    'admin_username' => env('ADMIN_USERNAME'),
    'admin_password' => env('ADMIN_PASSWORD'),
];
