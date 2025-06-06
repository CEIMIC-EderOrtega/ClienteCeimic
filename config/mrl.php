<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración del Servicio MRL
    |--------------------------------------------------------------------------
    |
    | Aquí se almacenan todas las variables de entorno para el servicio MRL.
    | Usamos este archivo para poder cachear la configuración en producción.
    |
    */

    'prod_mode' => env('MRL_PROD_MODE', true),

    'user' => env('MRL_USER'),

    'pass_prod' => env('MRL_PASS_PROD'),
    'pass_test' => env('MRL_PASS_TEST'),

    'login_url_prod' => env('MRL_LOGIN_URL_PROD'),
    'login_url_test' => env('MRL_LOGIN_URL_TEST'),

    'report_url_prod' => env('MRL_REPORTE_URL_PROD'),
    'report_url_test' => env('MRL_REPORTE_URL_TEST'),

    // Esta es la conexión a la BD que usará el controlador para las consultas de MRL
    'db_connection_legacy' => env('DB_CONNECTION_LEGACY_MRL', 'mylims'),
];
