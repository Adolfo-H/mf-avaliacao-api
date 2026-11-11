<?php

declare(strict_types=1);

namespace App\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class MfBillingAutoRules
{
    public const DEFAULT_TEMPLATES = [
        'Olá, {nome}! Aqui é da {estudio}. Ainda não identificamos a confirmação da mensalidade de {mes}, com vencimento em {data} ({valor}). Se já pagou, por favor nos avise. Obrigado!',
        'Oi, {nome}! Passando para lembrar da mensalidade de {mes}, vencida em {data}. Nosso controle ainda mostra {valor} em aberto. Se o Pix já foi feito, nos avise para atualizarmos.',
        'Olá, {nome}. Este é um lembrete da equipe {estudio} sobre a mensalidade de {mes} ({valor}). Caso já tenha realizado o pagamento, nos envie uma confirmação. Agradecemos!',
    ];

    public static function validateTemplates(array $templates, int $max): array
    {
        if (count($templates) !== 3 || $max < 1 || $max > 3) {
            throw new InvalidArgumentException('Cadastre três modelos e selecione entre um e três lembretes.');
        }
        $clean = [];
        foreach ($templates as $template) {
            if (! is_string($template) || mb_strlen(trim($template)) < 30 || mb_strlen(trim($template)) > 950) {
                throw new InvalidArgumentException('Cada mensagem deve conter entre 30 e 950 caracteres.');
            }
            $template = trim($template);
            preg_match_all('/\{([^{}]+)\}/u', $template, $matches);
            foreach ($matches[1] as $token) {
                if (! in_array($token, ['nome', 'mes', 'data', 'valor', 'estudio', 'dias_atraso'], true)) {
                    throw new InvalidArgumentException('Marcador não reconhecido: {'.$token.'}.');
                }
            }
            $clean[] = $template;
        }
        if (count(array_unique(array_slice($clean, 0, $max))) !== $max) {
            throw new InvalidArgumentException('Use uma mensagem diferente em cada etapa ativa.');
        }

        return $clean;
    }

    public static function schedule(string $due, int $first, int $interval, int $step, string $time): DateTimeImmutable
    {
        if ($first < 1 || $first > 30 || $interval < 2 || $interval > 30 || $step < 1 || $step > 3 || ! preg_match('/^(0[7-9]|1[0-8]):[0-5]\d$/D', $time)) {
            throw new InvalidArgumentException('Prazo, intervalo, etapa ou horário inválido.');
        }
        $tz = new DateTimeZone('America/Sao_Paulo');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $due.' '.$time, $tz);
        if (! $date || $date->format('Y-m-d H:i') !== $due.' '.$time) {
            throw new InvalidArgumentException('Data de vencimento inválida.');
        }

        return $date->add(new DateInterval('P'.($first + ($step - 1) * $interval).'D'));
    }

    /** Primeira tentativa: regra global OU limite individual de pagamento (inclusivo). */
    public static function firstReminderAt(
        string $due,
        string $referenceMonth,
        ?int $graceDay,
        int $firstAfterDays,
        string $sendTime
    ): DateTimeImmutable {
        if ($graceDay === null) {
            return self::schedule($due, $firstAfterDays, 5, 1, $sendTime);
        }
        if ($graceDay < 1 || $graceDay > 31) {
            throw new InvalidArgumentException('Dia limite de pagamento invalido.');
        }
        // Dia 31 em fevereiro usa o ultimo dia do mes. Se a fatura teve o
        // vencimento ajustado, nunca antecipar a mensagem para antes dele.
        $limit = MfBillingRules::dueDate($referenceMonth, $graceDay);
        $lastDayWithoutReminder = max($due, $limit);

        return self::schedule($lastDayWithoutReminder, 1, 5, 1, $sendTime);
    }

    public static function render(string $template, array $data): string
    {
        $replace = [
            '{nome}' => $data['nome'],
            '{mes}' => $data['mes'],
            '{data}' => $data['data'],
            '{valor}' => $data['valor'],
            '{estudio}' => $data['estudio'],
            '{dias_atraso}' => (string) $data['dias_atraso'],
        ];
        $text = strtr($template, $replace);
        // Obrigatório para evitar cobrança incorreta caso Fernando ainda não tenha dado baixa.
        if (! str_contains(mb_strtolower($text), 'já pag')) {
            $text .= "\nCaso já tenha pago, por favor desconsidere e nos avise para conferirmos.";
        }
        if (! str_contains(mb_strtoupper($text), 'SAIR')) {
            $text .= "\nSe preferir não receber lembretes por WhatsApp, responda SAIR.";
        }

        return $text;
    }
}
