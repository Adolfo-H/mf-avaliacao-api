<?php

namespace App\Support;

/**
 * Política mínima para recuperação administrativa por console.
 * Não salva, registra nem retorna a senha fornecida.
 */
final class AdminPasswordPolicy
{
    public static function isValid(string $password): bool
    {
        return strlen($password) >= 12
            && strlen($password) <= 200
            && preg_match('/[[:alpha:]]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1;
    }
}
