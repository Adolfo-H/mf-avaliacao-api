#!/bin/sh
set -eu

if [ "$(id -u)" -ne 0 ]; then
    echo 'ERRO: entrypoint requer root somente para preparar diretorio de sessao' >&2
    exit 1
fi

SESSION_DIR="${MF_WORKER_SESSION_DIR:-/var/data/whatsapp}"
case "$SESSION_DIR" in
    /var/data/*) ;;
    *) echo 'ERRO: MF_WORKER_SESSION_DIR precisa estar sob /var/data/' >&2; exit 1 ;;
esac

# Criar somente diretorios esperados, sem sobrescrever a sessao existente.
mkdir -p /var/data "$SESSION_DIR"
chmod 700 "$SESSION_DIR"
chown node:node /var/data "$SESSION_DIR"
if ! gosu node test -w "$SESSION_DIR"; then
    echo 'ERRO: disco persistente sem permissao de escrita para o processo' >&2
    exit 1
fi

cd /app
exec gosu node npm start
