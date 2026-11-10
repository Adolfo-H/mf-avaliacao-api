'use strict';
function makeApi(env = process.env, fetcher = fetch) {
  const raw = String(env.MF_WORKER_API_URL || '').trim().replace(/\/$/, '');
  const url = new URL(raw);
  if (url.protocol !== 'https:' || !url.pathname.endsWith('/api/v1') || url.username || url.password || url.search || url.hash) {
    throw new Error('MF_WORKER_API_URL deve ser HTTPS e terminar com /api/v1');
  }
  const token = String(env.MF_BILLING_LOCAL_WORKER_TOKEN || '');
  if (!/^[a-f0-9]{64}$/i.test(token)) throw new Error('Token do worker deve ter 64 caracteres hexadecimais');
  async function post(path, payload) {
    const allowed = /^heartbeat$|^claim$|^opt-out$|^jobs\/[0-9a-f-]{36}\/(check|ack)$/.test(path);
    if (!allowed) throw new Error('Rota de worker nao permitida');
    const abort = new AbortController();
    const timeout = setTimeout(() => abort.abort(), 25000);
    try {
      const response = await fetcher(url.toString() + '/billing/local-worker/' + path, {
        method:'POST', signal:abort.signal,
        headers:{'Accept':'application/json','Content-Type':'application/json','X-MF-Billing-Worker':token},
        body:JSON.stringify(payload), redirect:'error', cache:'no-store'
      });
      if (!response.ok) throw new Error('API status HTTP '+response.status);
      const json = await response.json();
      if (typeof json !== 'object' || json === null) throw new Error('Resposta API invalida');
      return json;
    } finally { clearTimeout(timeout); }
  }
  return {post};
}
module.exports = {makeApi};
