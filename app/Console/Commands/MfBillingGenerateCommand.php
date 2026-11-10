<?php
namespace App\Console\Commands;
use App\Services\MfBillingService;
use Illuminate\Console\Command;
final class MfBillingGenerateCommand extends Command {
    protected $signature='mf:billing-generate {--month= : Competência AAAA-MM}';
    protected $description='Gera mensalidades únicas; não envia WhatsApp, não registra pagamento.';
    public function handle(MfBillingService $billing): int {
        if (!config('mf_billing.enabled')) { $this->error('MF_BILLING_ENABLED desativado.'); return self::FAILURE; }
        try { $n=$billing->generate($this->option('month') ?: now('America/Sao_Paulo')->format('Y-m')); }
        catch (\Throwable $e) { $this->error('Geração não concluída. Confira competência, migração e configuração.'); return self::FAILURE; }
        $this->info("MF_MENSALIDADES_GERADAS: {$n}. Nenhuma mensagem enviada."); return self::SUCCESS;
    }
}
