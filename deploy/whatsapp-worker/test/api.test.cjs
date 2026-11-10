'use strict';
const test = require('node:test'); const assert = require('node:assert/strict');
const {makeApi} = require('../src/api.cjs');
test('somente URL https /api/v1 e token forte',()=>{
  const token='f'.repeat(64);
  assert.throws(()=>makeApi({MF_WORKER_API_URL:'http://exemplo.com/api/v1',MF_BILLING_LOCAL_WORKER_TOKEN:token}));
  assert.throws(()=>makeApi({MF_WORKER_API_URL:'https://exemplo.com/api/v1/billing',MF_BILLING_LOCAL_WORKER_TOKEN:token}));
  assert.throws(()=>makeApi({MF_WORKER_API_URL:'https://exemplo.com/api/v1',MF_BILLING_LOCAL_WORKER_TOKEN:'short'}));
});
test('API usa POST, token header privado e apenas rotas autorizadas',async()=>{
  const calls=[];const api=makeApi({MF_WORKER_API_URL:'https://api.example.com/api/v1',MF_BILLING_LOCAL_WORKER_TOKEN:'f'.repeat(64)},async(url,opts)=>{calls.push({url,opts});return {ok:true,json:async()=>({data:{matches_sender:true}})}});
  await api.post('heartbeat',{phone:'554599704892',ready:true});
  assert.equal(calls[0].opts.method,'POST');
  assert.equal(calls[0].opts.headers['X-MF-Billing-Worker'].length,64);
  assert.match(calls[0].url,/\/api\/v1\/billing\/local-worker\/heartbeat$/);
  await assert.rejects(api.post('../admin',{}));
});
