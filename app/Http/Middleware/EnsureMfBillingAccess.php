<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
final class EnsureMfBillingAccess {
    public function handle(Request $r, Closure $next): Response {
        $u=$r->user();
        abort_unless($u && $u->active && ($u->isAdmin() || $u->isReception()),403,'Mensalidades são restritas à administração e recepção.');
        abort_unless(config('mf_billing.enabled'),503,'Módulo de mensalidades ainda não ativado.');
        abort_unless(Schema::hasTable('mf_billing_accounts') && Schema::hasTable('mf_billing_invoices') && Schema::hasTable('mf_billing_reminders') && Schema::hasTable('mf_billing_events'),503,'Migração de mensalidades ainda não aplicada.');
        $response=$next($r);
        $response->headers->set('Cache-Control','private, no-store');
        $response->headers->set('Pragma','no-cache');
        return $response;
    }
}
