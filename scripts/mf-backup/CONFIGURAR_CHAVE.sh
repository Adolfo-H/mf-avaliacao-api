#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

if ! command -v gpg >/dev/null 2>&1; then
  echo 'ERRO: gpg nao instalado. Instale com: sudo apt-get install gnupg' >&2
  exit 1
fi

echo '===== CHAVE PUBLICA GPG PARA BACKUP ====='
echo 'Se ainda nao possui chave, crie uma chave protegida por senha:'
echo "  gpg --quick-generate-key 'MF Avaliacao Backup <backup@mf.local>' default default 2y"
echo 'Nunca coloque a chave privada, a senha ou a APP_KEY no GitHub.'
echo 'Chaves disponíveis:'
gpg --list-keys --keyid-format LONG || true
read -r -p 'Cole o fingerprint COMPLETO (40 ou 64 caracteres hexadecimais): ' fingerprint
fingerprint="$(printf '%s' "$fingerprint" | tr -d '[:space:]')"
if ! [[ "$fingerprint" =~ ^([A-Fa-f0-9]{40}|[A-Fa-f0-9]{64})$ ]]; then
  echo 'ERRO: fingerprint GPG invalido.' >&2
  exit 1
fi
if ! gpg --batch --list-keys "$fingerprint" >/dev/null 2>&1; then
  echo 'ERRO: chave publica nao encontrada no seu GPG.' >&2
  exit 1
fi
config_dir="$HOME/.config/mf-avaliacao"
mkdir -p "$config_dir"
chmod 700 "$config_dir"
tmp="$(mktemp "$config_dir/.recipient-XXXXXX")"
trap 'rm -f -- "$tmp"' EXIT
printf '%s\n' "$fingerprint" > "$tmp"
chmod 600 "$tmp"
mv -f -- "$tmp" "$config_dir/backup-recipient"
echo 'Configuracao salva com permissao 600.'
echo 'IMPORTANTE: exporte e guarde sua chave privada GPG em local seguro e separado do computador.'
echo 'IMPORTANTE: guarde a APP_KEY do Laravel no cofre de segredos; nao sera incluida no backup.'
