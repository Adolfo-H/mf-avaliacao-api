# MF Avaliação Física — Entrega técnica complementar (06/11/2026)

**Status:** pronto para regressão técnica e homologação com **dados fictícios**. **Não é liberação clínica para pacientes reais.**

## Entregas deste pacote

- **VO₂ por corrida 2.400 m / 1,5 milha:** cálculo indireto em tela separada, usando `VO₂ (ml/kg/min) = 3,5 + 483 / tempo (min)`, segundo ACSM Guidelines, 10ª edição. Input registrado e convertido para minutos a partir de segundos. Aceita 2.400 m ou o percurso exato de 1,5 milha (aprox. 2.414 m); não usa outras distâncias. Faz validação de idade adulta e medidas, sem prescrição ou classificação automática. Resultado **somente no GET de prévia**, sem atualizar `vo2_maxes.results`, PDFs ou comparações.
- **PAR-Q+ externo:** o questionário oficial continua acessível pelo endereço da colaboração PAR-Q+. O usuário pode registrar **versão/ano, data e referência interna** ao documento preenchido e arquivado fora do app. A API guarda o metadado no payload criptografado da anamnese, com autor e horário atribuídos pelo servidor. **Não** reproduz as perguntas, não importa respostas nem pressupõe aptidão; requer avaliação profissional do formulário completo.
- **Testes neuromotores:** a API informa se há dados mínimos para revisão manual (protocolo, valor/unidade, repetições/duração conforme cada método). Não inventa faixas etárias ou notas de corte; suas classificações persistidas continuam `null` até aprovar referências clínicas.
- **Interface:** link direcionado para corrida 2.400 m; Cooper só aparece quando protocolo Cooper está selecionado; formulário externo PAR-Q+ dentro da anamnese; orientação neuromotora mais objetiva.
- **Testes:** novas unidades para a equação e limites; testes de autenticação, auditabilidade e ausência de persistência; testes de rejeição de metadados indevidos no PAR-Q+.

**Nenhuma migration, alteração de credenciais, exclusão de registros ou escrita automática em TiDB/B2/Render.**

## Fontes e limites

1. ACSM's Guidelines for Exercise Testing and Prescription, 10th edition, seção de testes de campo para 1.5-mile run/walk: `VO₂ = 3,5 + 483 / tempo_min`. Consulta: https://www.exercisescienceacademy.online/_files/ugd/86c999_6c889a821c004e86bd71c8e3debfab50.pdf
2. PAR-Q+ Collaboration, versões oficiais: https://eparmedx.com/how-to-cite/ e https://eparmedx.com/print-versions-of-par-q/ . **A colaboração proíbe incorporar o formulário em pesquisas eletrônicas/derivativos sem consentimento escrito**. Não inserir o texto no banco nem transcrever ou editar perguntas sem autorização formal.
3. Equações existentes no sistema (Jackson–Pollock, Guedes, Weltman, Cooper e Rockport) permanecem **prévias matemáticas**. Ver `/docs/mf-avaliacao/ETAPA_10_HOMOLOGACAO_CLINICA.md` para o termo de homologação. Mesmo o IMC/FCmáx apresentado em prévia exige avaliação de escopo e população antes de utilização clínica.

## Matriz do que realmente falta para produção com dados reais

| Área | Técnica implementada? | Pendência externa obrigatória |
|---|---|---|
| Login, avaliação, fechamento, histórico, fotos, PDF básico | Sim, requer regressão funcional | Aceite final por perfil e revisão LGPD/privacidade |
| VO₂ 2.400 m | Prévia sim | Fonte/protocolo, condições de esforço, faixa de aplicação e assinatura do responsável |
| Cooper 12 min, Rockport, Pollock 3/7, Guedes, Weltman | Prévia sim | Verificar cada equação com 10 exemplos manuais e condições de aplicação |
| PAR-Q+ | Link oficial e registro de documento externo sim | Obter autorização se quiser incorporar questionário ao app; revisar o PDF original assinado e eventuais encaminhamentos |
| Bioimpedância | Registro de valores do equipamento sim | Definir fabricante, modelo, protocolo do equipamento, hidratação e limites |
| Flexibilidade e testes de resistência | Entrada e conferência dos dados sim | Fornecer tabela por sexo, idade, método, execução, tempo e unidade, com direitos de uso e aprovação profissional |
| Classificações clínicas automáticas e interpretação no PDF | Propositalmente bloqueadas | Homologação clínica escrita, bibliografia e teste de segurança |

### Para a profissional preencher antes de homologar qualquer tabela

1. Nome, conselho/registro, local e data da revisão.
2. Referência primária (autor/ano/edição), licença de reprodução e versão da norma.
3. População/amostra, faixa etária, sexo/variáveis relevantes e exclusões.
4. Protocolo de execução, equipamento, calibração, unidade e tempo (ex.: flexão em 60 segundos ou sem tempo).
5. Limites completos sem lacunas nem sobreposições e tratamento de valores extremos.
6. No mínimo **10 exemplos**: limites, igualdade exata, dados ausentes, negativos e grupos não elegíveis.
7. Responsável, assinatura, autorização escrita da ativação e hash da versão aprovada.

**Sem esses dados, a classificação deve permanecer indisponível**. A planilha CSV `REFERENCIAS_NEUROMOTORAS_A_PREENCHER.csv` é apenas modelo de solicitação e não contém limites inventados.

## Regressão com dados fictícios (aceite técnico)

1. Autenticar administrador; testar avaliador vinculado e recepção (acesso negado às prévias).
2. Avaliação em rascunho → VO₂ 2.400 m, 2.400 metros e 12:00 minutos → salvar → prévia **43,75 ml/kg/min**. Conferir `NÃO HOMOLOGADO`, sem comparação clínica ou PDF.
3. Distância 1.600 m ou tempo vazio → prévia recusa cálculo; teste Rockport/Cooper não pode ser oferecido como mesma fórmula.
4. Anamnese: abrir PAR-Q+ oficial fora do app; usar formulário de teste, guardar em local privado; registrar `2025`, data e referência `FORM-MF-001`; conferir status **referência registrada, sem liberação clínica**.
5. Deixar campos PAR-Q externo vazios e salvar os demais dados da anamnese: salvamento normal, sem marcação automática de aptidão.
6. Testes neuromotores: cadastrar repetição, protocolo, tempo e unidade. Conferir `x de 3 com dados mínimos`; nenhuma classificação deve surgir.
7. Concluir as sete seções fictícias; cancelar → continuar rascunho; confirmar → histórico e modo somente leitura.
8. PDF autenticado, fotos com consentimento, revogação, links de prévia e tela no iPhone Safari/Chrome.
9. Rodar TypeScript, ESLint, Expo Doctor, export PWA, e **CI API PHPUnit em MySQL descartável**, antes de mesclar cada PR.
10. Executar primeiro o deploy da API, depois do Mobile; verificar login e prévias. Não executar `migrate:fresh`, `key:generate` ou importações na base existente.

### Termo da profissional responsável

- Nome / registro: _______________________________________________
- Protocolos revisados: ___________________________________________
- Fonte / versão / população: ______________________________________
- Cálculos de referência independentes anexos: ______________________
- Decisão: [ ] Aprovado  [ ] Aprovado com restrições  [ ] Reprovado
- Assinatura / data: _______________________________________________

> Uma aprovação de build/CI não comprova aplicabilidade clínica. O registro de conclusão de avaliação é um **estado administrativo**, não uma declaração de aptidão física.
