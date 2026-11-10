'use strict';
// MF: correção idempotente e específica da biblioteca whatsapp-web.js 1.34.7.
// Executada durante npm install (postinstall). Nunca abre WhatsApp nem envia mensagens.
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const waPath = path.join(root, 'node_modules', 'whatsapp-web.js');
const meta = JSON.parse(fs.readFileSync(path.join(waPath, 'package.json'), 'utf8'));
if (meta.version !== '1.34.7') {
  throw new Error('Versão whatsapp-web.js inesperada: ' + meta.version + '. Não aplicar patch sem revisão.');
}
const filename = path.join(waPath, 'src', 'util', 'Injected', 'Utils.js');
const before = fs.readFileSync(filename, 'utf8');
const old = '.Msg.get(newMsgKey._serialized);';
const fixed = '.Msg.get(newMsgKey._serialized || newMsgKey.$1);';
const count = (s, part) => s.split(part).length - 1;
if (count(before, fixed) === 1 && count(before, old) === 0) {
  console.log('WHATSAPP_PATCH_PERSISTENTE: JA_APLICADO');
  process.exit(0);
}
if (count(before, old) !== 1 || count(before, fixed) !== 0) {
  throw new Error('Código interno da biblioteca mudou. Patch interrompido, sem modificações.');
}
const backup = filename + '.mf-original-1.34.7.bak';
if (!fs.existsSync(backup)) {
  fs.copyFileSync(filename, backup, fs.constants.COPYFILE_EXCL);
}
fs.writeFileSync(filename, before.replace(old, fixed), 'utf8');
if (count(fs.readFileSync(filename, 'utf8'), fixed) !== 1) {
  fs.copyFileSync(backup, filename);
  throw new Error('Patch não pôde ser confirmado; biblioteca restaurada.');
}
console.log('WHATSAPP_PATCH_PERSISTENTE: APLICADO_COM_BACKUP');
