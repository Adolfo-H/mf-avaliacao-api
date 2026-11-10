'use strict';
const test = require('node:test'); const assert = require('node:assert/strict');
const {equivalent,variants,idFromReply} = require('../src/phones.cjs');
const {resolveRecipient} = require('../src/resolve.cjs');
test('formato brasileiro com e sem nono digito e equivalente',()=>{
  assert.ok(equivalent('5545999704892','554599704892'));
  assert.deepEqual(variants('5545999704892'),['5545999704892','554599704892']);
  assert.equal(equivalent('5544999704892','554599704892'),false);
  assert.equal(equivalent('5545999704893','554599704892'),false);
});
test('ID c.us e lid aceitos, dominio estranho bloqueado',()=>{
  assert.equal(idFromReply({_serialized:'5545999704892@c.us'}),'5545999704892@c.us');
  assert.equal(idFromReply({_serialized:'123456789012345@lid'}),'123456789012345@lid');
  assert.throws(()=>idFromReply({_serialized:'12345@g.us'}));
});
test('LID exige relacao reversa exata e mesmo numero',async()=>{
  const good={getNumberId:async()=>({_serialized:'123456789012345@lid'}), getContactLidAndPhone:async()=>[{lid:'123456789012345@lid',pn:'554599704892@c.us'}]};
  assert.equal(await resolveRecipient(good,'5545999704892'),'123456789012345@lid');
  const bad={...good,getContactLidAndPhone:async()=>[{lid:'123456789012345@lid',pn:'554499704892@c.us'}]};
  await assert.rejects(resolveRecipient(bad,'5545999704892'));
  await assert.rejects(resolveRecipient({...good,getContactLidAndPhone:undefined},'5545999704892'));
});
test('respostas diferentes para 13 e 12 digitos bloqueadas',async()=>{
  let calls=0; const client={getNumberId:async()=>({_serialized:++calls===1?'123456789012345@lid':'987654321098765@lid'}),getContactLidAndPhone:async()=>[]};
  await assert.rejects(resolveRecipient(client,'5545999704892'));
});
test('numero nao registrado nao pode enviar',async()=>{
  await assert.rejects(resolveRecipient({getNumberId:async()=>null},'5545999704892'));
});
