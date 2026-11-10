'use strict';
/** Apenas telefones BR (55 + DDD + 8 ou 9 digitos). Nunca inferir numero a partir do LID. */
function digits(value) { return String(value ?? '').replace(/\D/g, ''); }
function validBrazil(value) { return /^55\d{10,11}$/.test(digits(value)); }
function equivalent(a, b) {
  const x = digits(a); const y = digits(b);
  if (!validBrazil(x) || !validBrazil(y)) return false;
  const short = n => n.length === 13 && n[4] === '9' ? n.slice(0, 4) + n.slice(5) : n;
  return short(x) === short(y);
}
function variants(number) {
  const n = digits(number);
  if (!validBrazil(n)) throw new Error('Telefone brasileiro invalido');
  if (n.length === 13 && n[4] === '9') return [n, n.slice(0, 4) + n.slice(5)];
  return [n];
}
function idFromReply(reply) {
  if (!reply || typeof reply !== 'object') throw new Error('WhatsApp nao localizou o numero');
  const fields = [reply._serialized, reply.$1].filter(v => typeof v === 'string' && v.length);
  if (!fields.length || new Set(fields).size !== 1) throw new Error('Identificador invalido ou divergente');
  const id = fields[0];
  if (!/^(?:55\d{10,11}@c\.us|\d{10,25}@lid)$/.test(id)) throw new Error('Dominio WhatsApp inesperado');
  const [user, server] = id.split('@');
  if (reply.user !== undefined && String(reply.user) !== user) throw new Error('Usuario inconsistente');
  if (reply.server !== undefined && String(reply.server) !== server) throw new Error('Dominio inconsistente');
  return id;
}
module.exports = {digits,validBrazil,equivalent,variants,idFromReply};
