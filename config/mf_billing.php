<?php
return [
    // Ative somente depois da migração e da homologação do módulo.
    'enabled' => (bool) env('MF_BILLING_ENABLED', false),
    'timezone' => 'America/Sao_Paulo',
    'studio_name' => env('MF_BILLING_STUDIO_NAME', 'MF Saúde & Movimento'),
    'studio_phone' => env('MF_BILLING_STUDIO_PHONE', ''),
    'reminder_cooldown_hours' => 24,
    'whatsapp' => [
        'enabled' => (bool) env('MF_WHATSAPP_ENABLED', false),
        'graph_version' => env('MF_WHATSAPP_GRAPH_VERSION', ''),
        'phone_number_id' => env('MF_WHATSAPP_PHONE_NUMBER_ID', ''),
        'token' => env('MF_WHATSAPP_TOKEN', ''),
        'template' => env('MF_WHATSAPP_TEMPLATE', 'mf_mensalidade_lembrete'),
        'language' => env('MF_WHATSAPP_TEMPLATE_LANGUAGE', 'pt_BR'),
        'template_approved' => (bool) env('MF_WHATSAPP_TEMPLATE_APPROVED', false),
        'app_secret' => env('MF_WHATSAPP_APP_SECRET', ''),
        'verify_token' => env('MF_WHATSAPP_VERIFY_TOKEN', ''),
    ],
];
