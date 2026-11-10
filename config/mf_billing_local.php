<?php

return [
    // Nunca expor token no Mobile e nunca ativar antes da homologação.
    'enabled' => (bool) env('MF_BILLING_LOCAL_AUTOMATION_ENABLED', false),
    'worker_token' => env('MF_BILLING_LOCAL_WORKER_TOKEN', ''),
    'max_daily_reminders' => 10,
    'max_dispatch_lag_hours' => 48,
    'max_worker_age_seconds' => 150,
];
