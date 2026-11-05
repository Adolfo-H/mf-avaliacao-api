#!/usr/bin/env python3
"""Diagnóstico somente leitura da consolidação clínica 08A–08F.

Não consulta banco, não lê .env, não envia informações à rede.
"""
from __future__ import annotations

import json
from pathlib import Path
import re
import sys

ROOT = Path.cwd()
MANIFEST = Path(__file__).with_name('modulos_08.json')
errors: list[str] = []
warnings: list[str] = []


def error(msg: str) -> None:
    errors.append(msg)
    print('FALHA:', msg)


def read(rel: str) -> str | None:
    f = ROOT / rel
    if not f.is_file():
        error(f'Arquivo ausente: {rel}')
        return None
    try:
        return f.read_text(encoding='utf-8-sig')
    except UnicodeError:
        error(f'Arquivo precisa estar em UTF-8: {rel}')
        return None


if not (ROOT/'artisan').is_file() or not (ROOT/'composer.json').is_file():
    sys.exit('ERRO: execute dentro de ~/projetos/mf-avaliacao-api')
manifest = json.loads(MANIFEST.read_text(encoding='utf-8'))
routes = read('routes/api.php')
for mod in manifest['modules']:
    for rel in mod['api_files']:
        if not (ROOT / rel).is_file():
            error(f"{mod['id']} — falta {rel}; instale o pacote original antes de prosseguir.")
    if routes is not None:
        mark = "'" + mod['api_route'] + "'"
        number = routes.count(mark)
        if number != 1:
            error(f"{mod['id']} — rota {mod['api_route']} aparece {number} vez(es) (esperado: 1).")
    print(f"CHECADO: {mod['id']} — {mod['name']}")

composition = read('app/Http/Controllers/Api/V1/AssessmentBodyCompositionController.php')
if composition is not None:
    # Verifica o bloco de cada protocolo sem misturar blocos adjacentes.
    keys = list(re.finditer(r"'key'\s*=>\s*'([^']+)'", composition))
    for key in ('pollock_7', 'pollock_3', 'guedes_3', 'bioimpedance', 'weltman'):
        found = [i for i, m in enumerate(keys) if m.group(1) == key]
        if len(found) != 1:
            error(f'Protocolo {key} ausente ou duplicado em composição corporal.')
            continue
        index = found[0]
        block = composition[keys[index].start(): keys[index+1].start() if index+1<len(keys) else len(composition)]
        if not re.search(r"'fields_configured'\s*=>\s*true\b", block):
            error(f'{key}: os campos precisam estar disponíveis (fields_configured=true).')
        if not re.search(r"'calculation_configured'\s*=>\s*false\b", block):
            error(f'{key}: resultado oficial deve continuar não homologado (calculation_configured=false).')
    if not re.search(r"'body_fat_percentage'\s*=>\s*null", composition):
        error('Fórmula oficial de gordura foi alterada: revisar homologação antes de publicar.')

request = read('app/Http/Requests/Api/V1/UpdateAssessmentBodyCompositionRequest.php')
if request is not None:
    for field in ("'bioimpedance'", "'weltman.abdomen_upper_cm'", "'weltman.abdomen_umbilicus_cm'", "'weltman.applicability_confirmed'"):
        if field not in request:
            error(f'Regra de validação não encontrada: {field}')
for model in ('AssessmentBodyComposition', 'AssessmentVo2Max'):
    p = read('app/Models/'+model+'.php')
    if p is not None and p.count('encrypted:array') < 2:
        error(f'{model}: payload/results devem permanecer criptografados.')

if errors:
    print(f'\nREPROVADO: {len(errors)} problema(s). Nenhum arquivo modificado.')
    sys.exit(1)
print('\nOK: 6 módulos, rotas, validações e bloqueios de resultado oficial conferidos.')
print('IMPORTANTE: isso não substitui a suíte Laravel ou a homologação clínica.')
