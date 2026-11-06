<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AdminPasswordPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Recuperação operacional local: só funciona em console interativo.
 * Nunca recebe senha por argumento, variável de ambiente ou arquivo.
 */
final class MfAdminAccessCommand extends Command
{
    protected $signature = 'mf:admin-acesso';

    protected $description = 'Lista administradores e permite criar o primeiro ou redefinir senha por console';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Use este comando somente num terminal interativo do servidor.');
            return self::FAILURE;
        }

        $this->warn('Execute apenas em um computador confiável. Nenhuma senha será exibida.');
        $this->line('Banco conectado: '.DB::connection()->getDatabaseName());

        $admins = User::query()
            ->where('role', UserRole::Admin->value)
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'active']);

        if ($admins->isEmpty()) {
            $this->warn('Nenhum administrador cadastrado neste banco.');

            if (! $this->confirm('Criar o primeiro administrador neste banco?', false)) {
                $this->info('Cancelado sem alterações.');
                return self::SUCCESS;
            }

            $email = mb_strtolower(trim((string) $this->ask('E-mail do administrador')));
            $name = trim((string) $this->ask('Nome do administrador'));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255 || $name === '' || strlen($name) > 180) {
                $this->error('Nome ou e-mail inválido. Nenhum usuário criado.');
                return self::FAILURE;
            }

            // Não promover silenciosamente usuários de outros perfis.
            if (User::query()->where('email', $email)->exists()) {
                $this->error('Este e-mail já pertence a um usuário. Nenhuma alteração feita.');
                return self::FAILURE;
            }

            $password = $this->requestStrongPassword();
            if ($password === null) {
                return self::FAILURE;
            }

            User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => UserRole::Admin,
                'active' => true,
            ]);
            unset($password);

            $this->info('Administrador cadastrado. Nenhuma senha foi registrada na saída.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Nome', 'E-mail', 'Ativo'],
            $admins->map(fn (User $admin): array => [
                $admin->id,
                $admin->name,
                $admin->email,
                $admin->active ? 'sim' : 'não',
            ])->all()
        );

        if (! $this->confirm('Redefinir a senha de um administrador existente?', false)) {
            $this->info('Cancelado sem alterações.');
            return self::SUCCESS;
        }

        $idInput = trim((string) $this->ask('Informe o ID do administrador listado'));
        if (! ctype_digit($idInput) || (int) $idInput < 1) {
            $this->error('ID inválido.');
            return self::FAILURE;
        }

        /** @var User|null $admin */
        $admin = $admins->firstWhere('id', (int) $idInput);
        if (! $admin) {
            $this->error('O ID informado não corresponde a um administrador.');
            return self::FAILURE;
        }

        if (! $admin->active) {
            $this->error('Administrador inativo. Nenhuma senha modificada; revise o cadastro antes.');
            return self::FAILURE;
        }

        if (! $this->confirm('Confirmar troca de senha e encerramento das sessões de '.$admin->email.'?', false)) {
            $this->info('Cancelado sem alterações.');
            return self::SUCCESS;
        }

        $password = $this->requestStrongPassword();
        if ($password === null) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($admin, $password): void {
            $admin->forceFill(['password' => Hash::make($password)])->save();
            // Revoga tokens Sanctum ativos após a recuperação da conta.
            $admin->tokens()->delete();
        });
        unset($password);

        $this->info('Senha alterada e sessões antigas revogadas.');
        return self::SUCCESS;
    }

    private function requestStrongPassword(): ?string
    {
        $password = (string) $this->secret('Nova senha (12 a 200 caracteres, letras e números)');
        $confirmation = (string) $this->secret('Repita a nova senha');

        if (! AdminPasswordPolicy::isValid($password) || ! hash_equals($password, $confirmation)) {
            $this->error('Senha insuficiente ou confirmação diferente. Nenhuma senha alterada.');
            unset($password, $confirmation);
            return null;
        }

        unset($confirmation);
        return $password;
    }
}
