<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
return [
    'disk' => env('TENANT_STORAGE_DISK', 'tenant-storage-local'),
    'signed_url_ttl' => (int) env('TENANT_STORAGE_SIGNED_URL_TTL', 300),
    'upload_ttl' => (int) env('TENANT_STORAGE_UPLOAD_TTL', 86400),
    'usage_ttl' => 300,
    'connection' => [
        'driver' => 's3',
        'key' => env('SUPABASE_STORAGE_ACCESS_KEY_ID'),
        'secret' => env('SUPABASE_STORAGE_SECRET_ACCESS_KEY'),
        'region' => env('SUPABASE_STORAGE_REGION'),
        'bucket' => env('SUPABASE_STORAGE_BUCKET'),
        'endpoint' => env('SUPABASE_STORAGE_ENDPOINT'),
        'use_path_style_endpoint' => true,
        'throw' => true,
        'options' => ['CacheControl' => 'private, no-store'],
        'http' => ['connect_timeout' => 5, 'timeout' => 60],
    ],
];
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
