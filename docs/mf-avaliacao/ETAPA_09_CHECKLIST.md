# Checklist realista de encerramento — MF Avaliação Física

## A. Consolidação técnica (Etapa 09)

- [ ] API: seis endpoints disponíveis exatamente uma vez e sob `auth:sanctum`.
- [ ] API: `ClinicalModulesIntegrationTest` aprovado, mais todos os testes anteriores.
- [ ] API: `composer audit --locked` sem alertas não aceitos.
- [ ] Mobile: seis telas de prévias presentes, com links e serviços autenticados.
- [ ] Mobile: TypeScript, Expo Doctor e ESLint aprovados.
- [ ] QA com dados fictícios em iPhone e, se aplicável, Android.
- [ ] Publicar os pacotes 08A–08F/09 em branches `feature`; GitHub Actions verde após o push e PR revisados.
- [ ] Confirmar que os campos Pollock 3 são selecionáveis e `calculation_configured=false` permanece.

## B. Homologação clínica (obrigatória antes de resultados oficiais)

- [ ] Pollock 3 e 7 dobras: documentar referência original, população/idade/sexo, medição e equação; conferir casos conhecidos.
- [ ] Guedes: validar sítios anatômicos, aplicabilidade por sexo e precisão/escopo.
- [ ] Cooper de 12 min: condições, contraindicações, distância e população; avaliação indireta, não diagnóstico.
- [ ] Rockport: validar idade, milha de 1609,344 m, frequência cardíaca ao final, peso e tempo.
- [ ] Weltman: validar os dois perímetros abdominais distintos e adequação populacional.
- [ ] Bioimpedância: identificar modelo/versão, origem dos valores, padronização da medição e precisão; não inventar fórmula universal.
- [ ] Tabelas de classificação: origem, critérios por idade e sexo, versão e aprovação escrita da profissional.
- [ ] Assinatura/registro da profissional e data de homologação para cada método, com testes antes/depois da alteração.
- [ ] Só então ativar e versionar cálculos e classificações *oficiais* no banco, comparação, gráficos e PDF.

## C. Segurança e operação

- [ ] Conferir login do administrador real, permissões de avaliador/recepção e mecanismo de recuperação de senha.
- [ ] Não criar administradores padrão com senhas fixas publicadas no código.
- [ ] Validar leitura/revogação de consentimento das fotografias e acesso seguro aos PDFs.
- [ ] Revisar LGPD (dados de saúde, consentimentos quando aplicáveis, finalidade, retenção, direitos e proteção).
- [ ] Revisar `npm audit`; não usar `npm audit fix --force` que rebaixe o Expo SDK.
- [ ] Automatizar backup criptografado e manter **cópia externa**. Executar restauração trimestral em ambiente isolado.
- [ ] Guardar chave privada GPG e `APP_KEY` em cofre seguro, separadas dos backups.
- [ ] Evitar `.env` versionado. Se já houver `.env` no GitHub, conferir que NÃO inclui segredos e rotacionar qualquer segredo exposto.
- [ ] Ambiente de produção com HTTPS, backups, monitoramento, política de acesso, logs e atualizações.

## D. Experiência e entrega

- [ ] Percorrer cadastro → anamnese → sete seções → concluir → histórico → comparação → fotos → gráficos → PDF.
- [ ] Testar perfis administrador, avaliador e recepção, além de sessões expiradas.
- [ ] Testar offline/reconexão, dados ausentes, telas vazias, fotos grandes e erros amigáveis.
- [ ] Criar build instalável Android e iOS com configurações de produção separadas.
- [ ] Executar homologação com a profissional e termo de aceite do aplicativo.
- [ ] Registrar versão final e plano de suporte/correções.

**Pronto para demonstração** depois de A + D com dados fictícios e prévias identificadas como não homologadas. **Pronto para produção com alunos reais** somente depois de B + C + D aprovados.
