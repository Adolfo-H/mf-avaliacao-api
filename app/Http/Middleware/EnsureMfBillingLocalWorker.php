<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMfBillingLocalWorker
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('mf_billing.enabled') && config('mf_billing_local.enabled'), 503, 'Conector local desativado.');
        $expected = (string) config('mf_billing_local.worker_token');
        $got = (string) $request->header('X-MF-Billing-Worker', '');
        abort_unless(strlen($expected) >= 40 && strlen($got) === strlen($expected) && hash_equals($expected, $got), 401, 'Conector não autorizado.');
        abort_unless(Schema::hasTable('mf_billing_auto_settings') && Schema::hasTable('mf_billing_auto_outbox'), 503, 'Migração do conector não aplicada.');
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
