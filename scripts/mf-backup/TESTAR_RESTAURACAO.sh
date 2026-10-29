#!/usr/bin/env bash
# Verifica hash, descriptografa temporariamente e OPCIONALMENTE restaura
# numa instancia MySQL 8.4 ISOLADA, sem portas e sem tocar na base da API.
set -Eeuo pipefail
umask 077

fail() { echo "ERRO: $*" >&2; exit 1; }
if [[ $# -lt 1 || $# -gt 2 ]]; then
  echo 'Uso: bash TESTAR_RESTAURACAO.sh /caminho/backup.tar.gz.gpg [--test-db]'
  exit 2
fi
archive="$(readlink -f -- "$1")"
mode="${2:---verificar}"
[[ "$mode" == '--verificar' || "$mode" == '--test-db' ]] || fail 'Modo invalido. Use --test-db para testar num MySQL temporario.'
[[ -f "$archive" && -f "$archive.sha256" ]] || fail 'Arquivo ou checksum .sha256 ausente.'
for bin in gpg gzip tar sha256sum mktemp; do
  command -v "$bin" >/dev/null || fail "Comando ausente: $bin"
done
( cd "$(dirname "$archive")" && sha256sum -c -- "$(basename "$archive").sha256" ) || fail 'SHA256 divergente.'

tmp_dir="$(mktemp -d "${TMPDIR:-/tmp}/mf-backup-validacao-XXXXXX")"
container_name=''
cleanup() {
  if [[ -n "$container_name" ]]; then docker rm -f -- "$container_name" >/dev/null 2>&1 || true; fi
  rm -rf -- "$tmp_dir"
}
trap cleanup EXIT

echo '===== TESTE DE DESCRIPTOGRAFIA E INTEGRIDADE ====='
# GPG autentica/descriptografa e tar valida o fluxo comprimido inteiro,
# sem jamais extrair fotografias clinicas em texto claro no disco.
gpg --decrypt -- "$archive" | tar -tzf - > "$tmp_dir/listagem.txt"
# Extrai APENAS o SQL comprimido e manifesto, nao fotos.
gpg --decrypt -- "$archive" | tar -xzf - --no-same-owner --no-same-permissions \
  -C "$tmp_dir" database.sql.gz manifest.txt
[[ -s "$tmp_dir/database.sql.gz" && -s "$tmp_dir/manifest.txt" ]] || fail 'Conteudo de backup incompleto.'
grep -qx 'format=MF-BACKUP-07' "$tmp_dir/manifest.txt" || fail 'Formato de backup nao reconhecido.'
grep -qx 'app_key_included=no' "$tmp_dir/manifest.txt" || fail 'Manifesto da chave de app invalido.'
gzip -t "$tmp_dir/database.sql.gz" || fail 'Arquivo SQL danificado.'
if [[ "$(grep -c '^database=' "$tmp_dir/manifest.txt")" -ne 1 ]]; then fail 'Nome do banco ausente ou duplicado.'; fi
db_name="$(sed -n 's/^database=//p' "$tmp_dir/manifest.txt")"
[[ "$db_name" =~ ^[a-zA-Z0-9_]+$ ]] || fail 'Nome do banco invalido no manifesto.'
photos="$(awk -F/ '$1=="student-photos" && $NF!="" {n++} END {print n+0}' "$tmp_dir/listagem.txt")"
printf 'Arquivo íntegro. Banco: %s. Entradas de fotografias: %s.\n' "$db_name" "$photos"

if [[ "$mode" == '--verificar' ]]; then
  echo 'Este modo nao importou o banco; rode --test-db para testar restauracao isolada.'
  exit 0
fi

command -v docker >/dev/null || fail 'Docker indisponivel para restauracao de teste.'
command -v openssl >/dev/null || fail 'OpenSSL indisponivel para senha de teste.'
password="$(openssl rand -hex 24)"
container_name="mf-backup-check-$$_$(date -u +%s)"
echo '===== TESTE DE RESTAURACAO EM MYSQL 8.4 DESCARTAVEL ====='
docker run --detach --name "$container_name" --network none \
  --env "MYSQL_ROOT_PASSWORD=$password" mysql:8.4 >/dev/null
ready=no
# mysqladmin ping retorna 0 ate quando a senha e recusada (ERROR 1045).
# A imagem mysql:8.4 usa --skip-networking durante o bootstrap; somente
# liberamos a importacao quando o servidor DEFINITIVO aceitar a senha correta.
for i in $(seq 1 60); do
  startup_state="$(docker exec --env "MYSQL_PWD=$password" "$container_name" \
    mysql --protocol=socket -uroot --batch --skip-column-names \
      --execute='SELECT @@GLOBAL.skip_networking;' 2>/dev/null)" || startup_state=''
  if [[ "$startup_state" == '0' ]]; then
    ready=yes
    break
  fi
  sleep 2
done
[[ "$ready" == yes ]] || fail 'MySQL temporario nao concluiu a inicializacao e a autenticacao.'
gzip -dc "$tmp_dir/database.sql.gz" | docker exec --interactive \
  --env "MYSQL_PWD=$password" "$container_name" mysql -uroot
count="$(docker exec --env "MYSQL_PWD=$password" "$container_name" mysql -uroot \
  --batch --skip-column-names \
  --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$db_name';")"
[[ "$count" =~ ^[0-9]+$ && "$count" -gt 0 ]] || fail 'Nenhuma tabela restaurada na instancia isolada.'
printf 'RESTAURACAO TESTADA: %s tabelas no MySQL descartavel.\n' "$count"
echo 'Nenhuma tabela, fotografia ou configuracao da API em uso foi substituida.'
