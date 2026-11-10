<?php
declare(strict_types=1);
namespace App\Support;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
final class MfBillingRules {
    public static function month(string $month): string {
        if (!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D', $month)) throw new InvalidArgumentException('Competência inválida. Use AAAA-MM.');
        return $month;
    }
    public static function dueDate(string $month, int $day): string {
        self::month($month);
        if ($day<1 || $day>31) throw new InvalidArgumentException('Vencimento deve ser entre 1 e 31.');
        $date = new DateTimeImmutable($month.'-01', new DateTimeZone('America/Sao_Paulo'));
        return $month.'-'.str_pad((string) min($day, (int)$date->format('t')),2,'0',STR_PAD_LEFT);
    }
    public static function status(string $due, bool $paid, bool $cancelled, string $today): string {
        if ($cancelled) return 'cancelled'; if ($paid) return 'paid';
        return $due<$today ? 'overdue' : ($due===$today ? 'due_today' : 'open');
    }
    public static function phone(?string $input): ?string {
        if ($input===null || trim($input)==='') return null;
        // País explícito obrigatório; não inventa DDI nem altera número do aluno.
        $explicitInternational = str_starts_with(trim($input), '+');
        $digits = preg_replace('/[\s()+.\-]/','',trim($input));
        if (!$explicitInternational && !preg_match('/^55\d{10,11}$/D', $digits)) throw new InvalidArgumentException('Inclua o DDI: +55 para Brasil, ou + seguido do código do país.');
        if (!preg_match('/^[1-9]\d{7,14}$/D', $digits)) throw new InvalidArgumentException('Informe WhatsApp com DDI e DDD (ex.: +55 45 99999-0000).');
        return $digits;
    }
    public static function money(int $cents): string { return 'R$ '.number_format($cents / 100, 2, ',', '.'); }
    public static function message(string $name, string $month, int $cents, string $due, string $studio): string {
        $first = explode(' ',trim($name))[0] ?: 'olá';
        return "Olá, {$first}! Aqui é a equipe {$studio}. Nossa conferência ainda não identificou a mensalidade de ".substr($month,5,2).'/'.substr($month,0,4).', de '.self::money($cents).', com vencimento em '.date('d/m/Y',strtotime($due)).". Caso já tenha pago, por favor nos avise para atualizarmos o registro. Obrigado! Para não receber lembretes por WhatsApp, responda SAIR.";
    }
}
