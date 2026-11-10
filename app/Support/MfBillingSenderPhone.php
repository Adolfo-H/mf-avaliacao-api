<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Compara o remetente informado no painel com o ID retornado pelo WhatsApp Web.
 * Algumas contas brasileiras sao apresentadas pela sessao web sem o nono digito.
 * Nao compara apenas DDD/final e nunca altera o telefone do cadastro.
 */
final class MfBillingSenderPhone
{
    public static function matches(?string $configured, ?string $connected): bool
    {
        if (! self::valid($configured) || ! self::valid($connected)) {
            return false;
        }

        if ($configured === $connected) {
            return true;
        }

        return self::sameBrazilianMobile($configured, $connected)
            || self::sameBrazilianMobile($connected, $configured);
    }

    private static function valid(?string $phone): bool
    {
        return is_string($phone) && preg_match('/^[1-9][0-9]{7,14}$/D', $phone) === 1;
    }

    private static function sameBrazilianMobile(string $long, string $short): bool
    {
        // 55 + DDD (2) + 9 adicional + numero movel antigo (8, com inicio 6-9).
        if (preg_match('/^55[1-9][0-9]9[6-9][0-9]{7}$/D', $long) !== 1 || strlen($short) !== 12) {
            return false;
        }

        return $short === substr($long, 0, 4).substr($long, 5);
    }
}
