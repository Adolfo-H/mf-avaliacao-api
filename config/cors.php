<?php

/**
 * CORS restrito aos endereços controlados pelos responsáveis pelo MF.
 * Preencha MF_PWA_ALLOWED_ORIGINS com os hosts HTTPS REAIS da interface web
 * separados por vírgula. Nunca use '*' para liberar dados de saúde.
 * O aplicativo usa Authorization: Bearer, sem cookies cross-site.
 */
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'MF_PWA_ALLOWED_ORIGINS',
            'http://localhost:8081,http://127.0.0.1:8081'
        ))
    ))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Cache-Control', 'X-Requested-With'],
    'exposed_headers' => ['Content-Disposition'],
    'max_age' => 600,
    'supports_credentials' => false,
];
