'use strict';
/**
 * MF Cloud Worker (instancia UNICA; Render Background Worker + Persistent Disk).
 * Nao abre porta HTTP, nao le conversas/historico, nao armazena textos em logs.
 * Envio SOMENTE se backend, operador e worker autorizarem simultaneamente.
 */
require('dotenv').config();
const fs = require('node:fs');
const path = require('node:path');
const qrcode = require('qrcode-terminal');
const {Client, LocalAuth} = require('whatsapp-web.js');
const {makeApi} = require('./api.cjs');
const {digits,validBrazil,equivalent} = require('./phones.cjs');
const {resolveRecipient} = require('./resolve.cjs');

const api = makeApi();
const allowSend = process.env.MF_WORKER_ALLOW_SEND === 'true';
const sessionDir = process.env.MF_WORKER_SESSION_DIR || '/var/data/whatsapp';
if (!path.isAbsolute(sessionDir)) throw new Error('Sessao precisa de caminho absoluto');
fs.mkdirSync(sessionDir, {recursive:true,mode:0o700});
const disk = fs.realpathSync(sessionDir);
if ((process.env.RENDER || process.env.IS_RENDER) && !disk.startsWith('/var/data/')) {
  throw new Error('No Render a sessao deve estar no DISCO PERSISTENTE em /var/data');
}

const client = new Client({
  authStrategy:new LocalAuth({dataPath:disk,clientId:'mf-cloud'}),
  puppeteer:{
    headless:true,
    executablePath:process.env.PUPPETEER_EXECUTABLE_PATH || undefined,
    args:['--no-sandbox','--disable-setuid-sandbox','--disable-dev-shm-usage']
  }
});
let ready = false, busy = false, sender = null, lastOK = 0;
let tickTimer = null, watchdogTimer = null, quitting = false;
const log = (...parts) => console.log(new Date().toISOString(),...parts);
const anonymize = s => String(s||'').replace(/\d{5,}/g,'[OCULTO]');
function fatal(reason) {
  log('CONEXAO_PERDIDA:',anonymize(reason));
  ready = false;
  if (!quitting) setTimeout(()=>{void stop(1)},3000).unref();
}
async function stop(code) {
  if (quitting) return;
  quitting = true;
  ready = false;
  clearInterval(tickTimer); clearInterval(watchdogTimer);
  try {if (sender) await api.post('heartbeat',{phone:sender,ready:false});} catch(_) {}
  try {await Promise.race([client.destroy(),new Promise(r=>setTimeout(r,5000))]);} catch(_) {}
  process.exit(code);
}
process.on('SIGINT',()=>void stop(0));
process.on('SIGTERM',()=>void stop(0));
process.on('unhandledRejection',e=>fatal('falha assincrona '+(e?.name||'rejection')));
process.on('uncaughtException',e=>fatal('falha inesperada '+(e?.name||'exception')));

client.on('qr',qr=>{
  ready = false;
  log('WHATSAPP_NAO_CONECTADO: novo pareamento necessario.');
  if (process.env.MF_ALLOW_QR_LOG === 'true') {
    console.log('QR_DE_VINCULACAO: confidencial; so abrir os logs com conta autorizada no Render.');
    qrcode.generate(qr,{small:true});
  } else log('QR_OCULTO: ative MF_ALLOW_QR_LOG=true temporariamente para vincular.');
});
client.on('authenticated',()=>log('WHATSAPP_AUTENTICADO: sincronizando...'));
client.on('auth_failure',()=>fatal('autenticacao recusada, requer novo pareamento'));
client.on('disconnected',()=>fatal('sessao desconectada pelo WhatsApp'));
client.on('ready',()=>{
  const actual = digits(client.info?.wid?.user);
  if (!validBrazil(actual)) return fatal('numero remetente nao reconhecido');
  sender = actual;
  ready = true; lastOK = Date.now();
  log('WHATSAPP_PAREADO: final',actual.slice(-4),'| envio automatico',allowSend?'LIBERAVEL':'BLOQUEADO');
  void tick();
  if (!tickTimer) tickTimer = setInterval(()=>void tick(),60000);
});

async function reconcileOptout(msg) {
  // Nao le historico, listas de contatos ou outras mensagens; so texto exato de descadastro.
  if (!allowSend || !ready || !msg || msg.fromMe) return;
  const content = String(msg.body||'').trim().toUpperCase();
  if (!['SAIR','PARAR','STOP','CANCELAR LEMBRETES'].includes(content)) return;
  const from = String(msg.from||'');
  let number = null;
  if (/^55\d{10,11}@c\.us$/.test(from)) number = from.split('@')[0];
  else if (/^\d{10,25}@lid$/.test(from) && typeof client.getContactLidAndPhone === 'function') {
    const mapping = await client.getContactLidAndPhone([from]);
    const matches = (Array.isArray(mapping)?mapping:[]).filter(x=>x?.lid===from && /^55\d{10,11}@c\.us$/.test(x?.pn||''));
    if (matches.length===1) number = matches[0].pn.split('@')[0];
  }
  if (!validBrazil(number)) { log('DESCADASTRO_NAO_RESOLVIDO: checar manualmente.'); return; }
  try {const result=await api.post('opt-out',{phone:number});log('DESCADASTRO_PROCESSADO:',Number(result.count||0),'contas');}
  catch(e){log('DESCADASTRO_PENDENTE: API indisponivel. Nao enviar novos lembretes ate conferir.');ready=false;fatal('falha de descadastro');}
}
client.on('message',msg=>{void reconcileOptout(msg).catch(()=>fatal('erro ao processar descadastro'));});

async function poll() {
  if (!ready || !sender || quitting) return;
  // Um poll por vez, inclusive apos atrasos da rede. Acoes sao sequenciais.
  const hb = await api.post('heartbeat',{phone:sender,ready:true});
  lastOK = Date.now();
  if (!hb.data?.matches_sender) {log('ENVIO_BLOQUEADO: numero remetente diferente do painel');return;}
  if (!allowSend || !hb.data?.enabled) {
    log('CONECTADO: envios pausados por configuracao do worker ou do aplicativo');return;
  }
  const claimed = await api.post('claim',{phone:sender});
  const job = claimed.data;
  if (!job) return;
  if (!/^[0-9a-f-]{36}$/i.test(String(job.uuid||'')) || !validBrazil(job.recipient)
      || typeof job.message!=='string' || job.message.length<30 || job.message.length>2000) {
    log('TRABALHO_INCONSISTENTE: nenhuma mensagem enviada'); return;
  }
  let target;
  try {target = await resolveRecipient(client,job.recipient);}
  catch(e) {
    // A reserva fica retida (sem retry automatico); nunca mandar para numero presumido.
    log('DESTINO_NAO_VALIDADO: reserva retida, sem envio. Motivo:',anonymize(e?.message));
    return;
  }
  const checked = await api.post('jobs/'+job.uuid+'/check',{phone:sender});
  if (!checked.data?.can_send) {log('COBRANCA_ALTERADA: sem envio');return;}
  if (!equivalent(checked.data.recipient,job.recipient) || checked.data.message!==job.message) {
    log('CONTEUDO_MUDOU: nenhuma mensagem enviada, conciliacao pendente');
    // Ja foi marcada como "sending"; sem ACK e sem retry por seguranca.
    return;
  }
  let status='unknown', providerId=null, errorCode='envio_incerto';
  try {
    // ÚNICA tentativa. Nunca repetir em timeout, reboot ou retorno incerto.
    const sent=await client.sendMessage(target,job.message,{sendSeen:false});
    const id=sent?.id?._serialized||sent?.id?.$1;
    if (typeof id==='string' && id.length>5) {status='sent';providerId=id;errorCode=null;}
    else {log('ENVIO_INCERTO: resposta sem identificador; conferir antes de reenviar');}
  } catch(e) {
    log('ENVIO_INCERTO: WhatsApp retornou erro. Nenhum reenvio automatico.',anonymize(e?.name));
  }
  try {await api.post('jobs/'+job.uuid+'/ack',{status,provider_id:providerId,error_code:errorCode});}
  catch(e) {log('ACK_PENDENTE: resultado nao conciliado. Nao repetir o envio.');fatal('resultado sem confirmacao na API');}
  log('REMINDER_RESULTADO:',status,'| etapa',job.step);
}
async function tick() {
  if (busy || quitting || !ready) return;
  busy=true;
  try {
    // Detecta sessão autenticada que deixou de ser CONNECTED antes que o painel a trate como online.
    const state=await Promise.race([client.getState(),new Promise((_,reject)=>setTimeout(()=>reject(new Error('estado sem resposta')),30000))]);
    if (state !== 'CONNECTED') {ready=false;return fatal('estado do WhatsApp nao conectado');}
    await poll();
  } catch(e) {
    log('API_OU_WHATSAPP_INDISPONIVEL:',anonymize(e?.message||'erro'));
    // Falha momentanea de API: sem enviar; heartbeat expirara em 150s no painel.
    if (Date.now()-lastOK>180000) return fatal('heartbeat da API indisponivel');
  } finally {busy=false;}
}
watchdogTimer=setInterval(()=>{
  if (ready && busy && Date.now()-lastOK>300000) fatal('worker sem resposta por cinco minutos');
},60000);
log('MF_CLOUD_WORKER_INICIANDO. MODO_ENVIO:',allowSend?'ATIVADO':'DESATIVADO');
log('ATENCAO: mantenha uma unica instancia; sessao em disco persistente, nao em banco.');
client.initialize().catch(e=>fatal(e?.name||'erro ao iniciar navegador'));
