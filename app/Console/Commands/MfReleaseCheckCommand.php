<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Diagnostico de prontidao para publicacao.
 * NAO gera chaves, nao migra banco, nao altera configuracao ou arquivos.
 * A aprovacao clinica/LGPD e o backup externo sao etapas MANUAIS.
 */
final class MfReleaseCheckCommand extends Command
{
    protected $signature = 'mf:release-check
        {--strict : Falha se a configuracao automatica nao estiver pronta para producao}
        {--check-db : Apenas testa a conexao com o banco atual, sem consultas de escrita}';

    protected $description = 'Confere configuracoes minimas de release sem revelar segredos nem modificar dados';

    public function handle(): int
    {
        $strict = (bool) $this->option('strict');
        $failures = 0;

        $environment = (string) config('app.env', '');
        $appUrl = trim((string) config('app.url', ''));
        $appKey = config('app.key');
        $dbDriver = (string) config('database.default', '');
        $photoDiskName = (string) config('student-photos.disk', 'student_photos_local');
        $photoConfig = config("filesystems.disks.{$photoDiskName}");
        $photoDriver = is_array($photoConfig) ? ($photoConfig['driver'] ?? '') : '';
        $privatePhotos = is_array($photoConfig)
            && ($photoConfig['visibility'] ?? null) === 'private'
            && in_array($photoDriver, ['local', 's3'], true);
        $expiration = config('sanctum.expiration');

        $this->line('MF Avaliacao Fisica - diagnostico de release (somente leitura)');
        $this->line($strict ? 'Modo: producao estrita' : 'Modo: desenvolvimento / diagnostico');

        $checks = [
            ['APP_ENV configurado como production', $environment === 'production'],
            ['APP_DEBUG desativado', config('app.debug') === false],
            ['APP_URL e HTTPS publico', $this->publicHttpsUrl($appUrl)],
            ['APP_KEY definida (valor nunca exibido)', is_string($appKey) && strlen($appKey) >= 32],
            ['Banco com driver de servidor (mysql/mariadb/pgsql)', in_array($dbDriver, ['mysql', 'mariadb', 'pgsql'], true)],
            ['Fotografias em armazenamento privado', $privatePhotos],
            ['Expiracao de tokens Sanctum configurada', is_numeric($expiration) && (int) $expiration > 0],
            ['Script de backup clinico existente', is_file(base_path('scripts/mf-backup/FAZER_BACKUP.sh'))],
        ];

        foreach ($checks as [$description, $passed]) {
            if ($passed) {
                $this->info('OK      '.$description);
            } else {
                $failures++;
                $this->warn('PENDENTE '.$description);
            }
        }

        if ($this->option('check-db')) {
            try {
                \Illuminate\Support\Facades\DB::connection()->getPdo();
                $this->info('OK      Conexao ao banco acessivel (sem comandos de escrita)');
            } catch (\Throwable $exception) {
                $failures++;
                $this->warn('PENDENTE Conexao ao banco indisponivel');
            }
        }

        $this->newLine();
        $this->warn('AINDA EXIGEM VERIFICACAO HUMANA: HTTPS e proxy confiavel, isolamento do banco e fotos,');
        $this->warn('backup criptografado externo + APP_KEY/GPG guardadas, LGPD, contas,');
        $this->warn('consentimentos, homologacao clinica assinada e aceite em Android/iPhone.');
        $this->line('Nenhum dado ou configuracao foi alterado. Nenhum segredo foi exibido.');

        if ($strict && $failures > 0) {
            $this->error("Release BLOQUEADO: {$failures} verificacao(oes) automatica(s) pendente(s).");
            return self::FAILURE;
        }

        if ($failures > 0) {
            $this->warn("Diagnostico encerrado com {$failures} pendencia(s) para ambiente de producao.");
        } else {
            $this->info('Verificacoes automaticas OK. Isso NAO substitui o aceite humano.');
        }

        return self::SUCCESS;
    }

    private function publicHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false; // Evita aprovar um IP/ambiente de desenvolvimento como dominio final.
        }
        return str_contains($host, '.') && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
