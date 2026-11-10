'use strict';
const {digits,validBrazil,equivalent,variants,idFromReply} = require('./phones.cjs');
/**
 * Consulta exclusivamente os numeros informados. @lid e opaco; nao e telefone.
 * Mapeamento reverso confirmado e obrigatorio para qualquer @lid usado em cobranca.
 */
async function resolveRecipient(client, rawPhone) {
  const phone = digits(rawPhone);
  if (!validBrazil(phone)) throw new Error('Destinatario sem DDI e DDD validos');
  const ids = [];
  for (const requested of variants(phone)) {
    const answer = await client.getNumberId(requested);
    if (!answer) continue;
    const id = idFromReply(answer);
    if (id.endsWith('@c.us') && !equivalent(id.split('@')[0],phone)) {
      throw new Error('WhatsApp retornou telefone diferente');
    }
    ids.push(id);
  }
  if (!ids.length) throw new Error('Telefone nao encontrado no WhatsApp');
  if (new Set(ids).size !== 1) throw new Error('Consultas retornaram contas diferentes');
  const id = ids[0];
  if (id.endsWith('@lid')) {
    if (typeof client.getContactLidAndPhone !== 'function') throw new Error('Confirmacao reversa de LID indisponivel');
    const mapping = await client.getContactLidAndPhone([id]);
    if (!Array.isArray(mapping)) throw new Error('Confirmacao reversa de LID invalida');
    const matches = mapping.filter(item => item && item.lid === id && typeof item.pn === 'string');
    if (!matches.length || matches.some(x => !/^55\d{10,11}@c\.us$/.test(x.pn) || !equivalent(x.pn.split('@')[0],phone))) {
      throw new Error('LID nao corresponde ao telefone do destinatario');
    }
  }
  return id;
}
module.exports = {resolveRecipient};
