'use strict';
const test = require('node:test');const assert=require('node:assert/strict');const fs=require('node:fs');
const worker=fs.readFileSync(require('node:path').join(__dirname,'../src/main.cjs'),'utf8');
test('worker exige autorizacao dupla e nao envia sem check',()=>{
  assert.match(worker,/process\.env\.MF_WORKER_ALLOW_SEND === 'true'/);
  assert.match(worker,/hb\.data\?\.enabled/);
  assert.match(worker,/\/check/);
  assert.match(worker,/checked\.data\?\.can_send/);
});
test('envio unico por reserva; falha ou resposta incerta nao causam repeticao automatica',()=>{
  assert.equal((worker.match(/client\.sendMessage\(/g)||[]).length,1);
  assert.match(worker,/status='unknown'/);
  assert.match(worker,/errorCode='envio_incerto'/);
});
test('opt-out nao consulta o historico de conversas',()=>{
  assert.match(worker,/client\.on\('message'/);
  assert.doesNotMatch(worker,/fetchMessages\(|getChats\(|getContacts\(/);
});
