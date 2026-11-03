#!/bin/sh
set -eu

# Nao use esta imagem com dados reais de pacientes no plano Free.
# O filesystem no Render Free e descartavel: fotos e arquivos podem sumir.
if [ "${APP_ENV:-}" != "production" ]; then
    echo "ERRO: APP_ENV deve ser production no Render." >&2
    exit 1
fi
if [ "${APP_DEBUG:-false}" != "false" ]; then
    echo "ERRO: APP_DEBUG deve ser false no Render." >&2
    exit 1
fi
if [ -z "${APP_KEY:-}" ]; then
    echo "ERRO: defina APP_KEY no painel Render (nao no Git)." >&2
    exit 1
fi

if [ "${PORT:-10000}" != "10000" ]; then
    echo "ERRO: esta imagem usa a porta 10000. Ajuste PORT=10000 no Render." >&2
    exit 1
fi

# Intencional: SEM migrations, seeds, recuperacao de senha ou reset automatico.
# Banco remoto externo e fotos duraveis serao configurados em etapas separadas.
echo "Iniciando API MF (demo). Banco e armazenamento nao sao validados no /health." >&2
exec apache2-foreground
