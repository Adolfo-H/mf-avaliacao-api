#!/usr/bin/env bash
# Somente leitura do ambiente real + PHPUnit no banco 'testing' isolado.
set -Eeuo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/../.."
if [[ ! -f artisan || ! -x ./vendor/bin/sail ]]; then
  echo 'ERRO: execute a partir da API Laravel com Sail instalado.' >&2
  exit 1
fi

echo '===== 1. COMANDO DE RELEASE (DIAGNOSTICO, SEM ALTERAR .env) ====='
./vendor/bin/sail artisan mf:release-check

echo '===== 2. NOVOS TESTES ====='
./vendor/bin/sail artisan test --filter=MfReleaseCheckCommandTest

echo '===== 3. SUITE COMPLETA (BANCO TESTING DO PHPUNIT) ====='
./vendor/bin/sail artisan test

echo '===== 4. AUDITORIA DE DEPENDENCIAS PHP ====='
./vendor/bin/sail composer audit --locked

echo '===== 5. GIT DIFF CHECK ====='
git diff --check

echo 'API: testes aprovados. Ainda nao e uma aprovacao de producao.'
