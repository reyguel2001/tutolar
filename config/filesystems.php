<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /**
         * Fotos de perfil.
         *
         * Van directamente bajo la raíz web, no en `storage/app/public`, para
         * no depender de `php artisan storage:link`: ese enlace simbólico es
         * un paso más que hay que acordarse de dar y que en Windows pide
         * permisos de administrador. Las fotos de perfil son públicas por
         * naturaleza —se muestran en cada pantalla— así que nada se gana
         * escondiéndolas detrás de un enlace.
         *
         * La carpeta se crea sola la primera vez que alguien sube una foto.
         */
        'avatares' => [
            'driver' => 'local',
            'root' => public_path('avatares'),
            'url' => '/avatares',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
