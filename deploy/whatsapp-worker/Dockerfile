# MF Cloud WhatsApp Worker: processo independente, um unico navegador/sessao.
FROM node:22-bookworm-slim
ENV NODE_ENV=production \
    PUPPETEER_SKIP_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
      ca-certificates chromium gosu fonts-liberation fonts-noto-color-emoji \
      libnss3 libnspr4 libasound2 libatk1.0-0 libatk-bridge2.0-0 \
      libcups2 libx11-xcb1 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 \
      libgbm1 libgtk-3-0 libpango-1.0-0 libcairo2 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY package.json package-lock.json ./
COPY scripts ./scripts
# O postinstall verifica a versao e aplica um patch localizado; falha se mudou.
RUN npm ci --omit=dev --no-audit --no-fund \
    && npm run prestart
COPY src ./src
COPY test ./test
RUN node --check src/main.cjs && node --test test/*.test.cjs \
    && mkdir -p /var/data/whatsapp \
    && chown -R node:node /var/data

# A permissao do volume e acertada no runtime: o mount no Render pode
# sobrepor permissoes preparadas durante o Docker build.
COPY scripts/start-container.sh /usr/local/bin/mf-start-worker
RUN chmod 755 /usr/local/bin/mf-start-worker
ENTRYPOINT ["/usr/local/bin/mf-start-worker"]
