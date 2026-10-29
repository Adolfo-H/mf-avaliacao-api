#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
api_dir="${MF_API_DIR:-$HOME/projetos/mf-avaliacao-api}"
script="$api_dir/scripts/mf-backup/FAZER_BACKUP.sh"
[[ -f "$script" ]] || { echo 'ERRO: instale o pacote na API primeiro.' >&2; exit 1; }
[[ -f "$HOME/.config/mf-avaliacao/backup-recipient" ]] || { echo 'ERRO: configure GPG antes.' >&2; exit 1; }
command -v crontab >/dev/null || { echo 'ERRO: crontab nao instalado.' >&2; exit 1; }

printf '%s\n' 'ATENCAO: cron no WSL so roda se a distribuicao e o daemon cron estiverem ativos.'
printf '%s\n' 'Para producao, configure agendamento no servidor ligado e alertas de falha.'
read -r -p 'Criar agendamento diario local as 02:15? Digite SIM: ' ok
[[ "$ok" == SIM ]] || { echo 'Cancelado.'; exit 0; }
backup_dir="${MF_BACKUP_DIR:-$HOME/MF_Avaliacao_Backups/clinico}"
mkdir -p "$backup_dir"; chmod 700 "$backup_dir"
existing="$(crontab -l 2>/dev/null || true)"
marker='MF-AVALIACAO-BACKUP-07'
if printf '%s\n' "$existing" | grep -Fq "$marker"; then
  echo 'Agendamento ja existe; nao foi duplicado.'; exit 0
fi
escaped_script="$(printf '%q' "$script")"
escaped_log="$(printf '%q' "$backup_dir/cron.log")"
line="15 2 * * * /usr/bin/env bash $escaped_script >> $escaped_log 2>&1 # $marker"
printf '%s\n%s\n' "$existing" "$line" | crontab -
echo 'Agendamento criado. Teste manualmente o backup e a restauracao antes de depender do cron.'
