'use strict';
// Executa ANTES de npm start. A versão sem patch NUNCA iniciará silenciosamente.
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const waDir = path.join(root, 'node_modules', 'whatsapp-web.js');
const pkg = JSON.parse(fs.readFileSync(path.join(waDir, 'package.json'), 'utf8'));
if (pkg.version !== '1.34.7') {
  console.error('ERRO: versão da biblioteca alterada. Homologue antes de reiniciar.');
  process.exit(1);
}
const file = path.join(waDir, 'src', 'util', 'Injected', 'Utils.js');
const txt = fs.readFileSync(file, 'utf8');
const marker = '.Msg.get(newMsgKey._serialized || newMsgKey.$1);';
if (txt.split(marker).length - 1 !== 1) {
  console.error('ERRO: a correção do WhatsApp foi removida. Rode npm install ou o script de manutenção.');
  process.exit(1);
}
console.log('WHATSAPP_ID_CORRIGIDO: OK (biblioteca 1.34.7)');
