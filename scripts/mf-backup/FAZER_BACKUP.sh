#!/usr/bin/env bash
# Copia logica do MySQL 8.4 + fotografias privadas, criptografada para chave GPG.
# Nao copia .env, APP_KEY, credenciais, logs ou node_modules.
set -Eeuo pipefail
umask 077

fail() { printf 'ERRO: %s\n' "$*" >&2; exit 1; }
api_dir="${MF_API_DIR:-$HOME/projetos/mf-avaliacao-api}"
backup_dir="${MF_BACKUP_DIR:-$HOME/MF_Avaliacao_Backups/clinico}"
recipient_file="$HOME/.config/mf-avaliacao/backup-recipient"
[[ -f "$api_dir/compose.yaml" && -f "$api_dir/.env" ]] || fail 'Diretorio da API ou .env ausente.'
[[ -f "$recipient_file" ]] || fail 'Configure uma chave GPG com CONFIGURAR_CHAVE.sh primeiro.'
for bin in docker gpg tar gzip sha256sum mktemp; do
  command -v "$bin" >/dev/null 2>&1 || fail "Comando necessario nao encontrado: $bin"
done
recipient="$(tr -d '[:space:]' < "$recipient_file")"
[[ "$recipient" =~ ^([A-Fa-f0-9]{40}|[A-Fa-f0-9]{64})$ ]] || fail 'Fingerprint invalido no arquivo de configuracao.'
gpg --batch --list-keys "$recipient" >/dev/null 2>&1 || fail 'Chave publica GPG nao disponivel.'

# Bloqueia instalacoes em que as fotografias estao em outro disco (S3 etc.).
photo_disk="$(sed -n 's/^STUDENT_PHOTO_DISK=//p' "$api_dir/.env" | tail -n 1 | tr -d '\r\"' | tr -d "'" | xargs)"
photo_disk="${photo_disk:-student_photos_local}"
[[ "$photo_disk" == 'student_photos_local' ]] || fail "Disco de fotos '$photo_disk' nao suportado por este backup local. Adapte a exportacao antes de continuar."

mkdir -p -- "$backup_dir"
chmod 700 "$backup_dir"
tmp_dir="$(mktemp -d "$backup_dir/.temp-XXXXXX")"
partial=''
cleanup() {
  if [[ -n "$partial" ]]; then rm -f -- "$partial"; fi
  rm -rf -- "$tmp_dir"
}
trap cleanup EXIT
cd "$api_dir"
docker compose version >/dev/null 2>&1 || fail 'Docker Compose indisponivel.'

echo '===== BACKUP CLINICO MF (SOMENTE LEITURA NA ORIGEM) ====='
# O nome do banco e lido de dentro do container; nao exibimos segredos.
db_name="$(docker compose exec -T mysql sh -ceu 'printf "%s" "$MYSQL_DATABASE"')" || fail 'Falha ao obter o nome do banco.'
[[ "$db_name" =~ ^[a-zA-Z0-9_]+$ ]] || fail 'Nome do banco fora do padrao seguro.'

# single-transaction oferece snapshot consistente para tabelas InnoDB.
# O conjunto banco+fotos NAO e uma transacao atomica: evite uploads durante o backup.
docker compose exec -T mysql sh -ceu '
  export MYSQL_PWD="${MYSQL_ROOT_PASSWORD:?}"
  exec mysqldump --user=root --single-transaction --quick \
    --hex-blob --routines --triggers --events --no-tablespaces \
    --set-gtid-purged=OFF --databases "${MYSQL_DATABASE:?}"
' | gzip -1 > "$tmp_dir/database.sql.gz"
[[ -s "$tmp_dir/database.sql.gz" ]] || fail 'Dump do banco vazio.'
gzip -t "$tmp_dir/database.sql.gz" || fail 'Dump SQL corrompido.'

photo_root="$api_dir/storage/app/private"
photo_exists=no
if [[ -d "$photo_root/student-photos" ]]; then photo_exists=yes; fi
{
  echo 'format=MF-BACKUP-07'
  printf 'created_utc=%s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
  printf 'database=%s\n' "$db_name"
  echo 'photo_disk=student_photos_local'
  printf 'photos_present=%s\n' "$photo_exists"
  printf 'git_revision=%s\n' "$(git rev-parse --short HEAD 2>/dev/null || echo desconhecida)"
  echo 'app_key_included=no'
} > "$tmp_dir/manifest.txt"

base="mf-clinico_$(date -u '+%Y%m%d_%H%M%S')_$$.tar.gz.gpg"
output="$backup_dir/$base"
partial="$output.partial"
echo 'Gerando arquivo criptografado (nenhum SQL/foto sera enviado ao GitHub)...'
if [[ "$photo_exists" == yes ]]; then
  tar --create --gzip --file - \
    -C "$tmp_dir" database.sql.gz manifest.txt \
    -C "$photo_root" student-photos \
  | gpg --batch --yes --trust-model always --recipient "$recipient" \
      --encrypt --output "$partial"
else
  echo 'AVISO: pasta local de fotos ainda nao existe; backup contem apenas o banco.'
  tar --create --gzip --file - -C "$tmp_dir" database.sql.gz manifest.txt \
  | gpg --batch --yes --trust-model always --recipient "$recipient" \
      --encrypt --output "$partial"
fi
[[ -s "$partial" ]] || fail 'Arquivo criptografado vazio.'
mv -- "$partial" "$output"
partial=''
( cd "$backup_dir" && sha256sum "$base" > "$base.sha256" )
chmod 600 "$output" "$output.sha256"
echo "BACKUP OK: $output"
echo 'VERIFICACAO: TESTAR_RESTAURACAO.sh caminho_do_arquivo.tar.gz.gpg'
echo 'Mantenha copia criptografada fora desta maquina e guarde GPG + APP_KEY separadamente.'
