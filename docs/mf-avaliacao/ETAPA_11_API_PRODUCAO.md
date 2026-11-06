# MF Avaliação Física — Etapa 11: API de produção (preparação)

**Atenção:** este documento não implanta um servidor; ele serve para impedir uma publicação insegura.
A API contém informações de saúde e fotografias privadas. Os dados reais ficam no servidor escolhido pela MF, nunca no GitHub ou no build do aplicativo.

## Diferenças entre ambientes

- **WSL/Sail local:** permanece `APP_ENV=local`, `APP_DEBUG=true` se necessário, banco local e URL HTTP de desenvolvimento. **Não altere o `.env` local para simular produção.** Rode `mf:release-check` sem `--strict` para identificar pendências.
- **Homologação:** servidor **separado**, banco e armazenamento privados com dados fictícios, domínio válido e HTTPS.
- **Produção:** servidor HTTPS próprio, banco não exposto à internet, backups externos verificados, acesso administrativo mínimo, logs protegidos, procedimentos de recuperação e revisão LGPD.

## Comandos de verificação (somente leitura na API em uso)

```bash
cd ~/projetos/mf-avaliacao-api
./vendor/bin/sail artisan mf:release-check
```

Em **um servidor de produção já configurado**, execute:

```bash
php artisan mf:release-check --strict --check-db
```

O comando verifica `APP_ENV`, `APP_DEBUG`, URL HTTPS, existência de `APP_KEY` (sem a mostrar), tipo de banco, armazenamento privado, expiração Sanctum e presença dos scripts de backup. Com `--check-db`, apenas testa a conexão ao banco, sem atualizar tabelas. **Mesmo com resultado aprovado, não substitui o checklist humano.**

## Pré-requisitos de servidor

1. HTTPS válido (`APP_URL=https://DOMINIO-REAL`) e proxy reverso confiável. Não usar IP privado, WSL ou servidor do Expo como API final.
2. `APP_ENV=production`, `APP_DEBUG=false`, logs com acesso restrito; manter e guardar a `APP_KEY` original para descriptografar dados históricos.
3. Banco MySQL/MariaDB/PostgreSQL isolado da internet e credenciais específicas, sem usar root no app.
4. Armazenamento de fotos privado e persistente (`student_photos_local` ou outro provedor privado validado); não colocar em `public/storage`.
5. Backup criptografado do banco e das fotografias **fora do próprio servidor**, com chave GPG e `APP_KEY` em cofre separado, teste periódico de restauração em ambiente isolado.
6. Revisar regras de autorização por perfil, revogação de consentimento, política de retenção de dados, segurança de tokens, logs e direitos LGPD com responsável competente.
7. Homologação das fórmulas e tabelas **assinada** antes de liberar interpretações clínicas ou resultados oficiais.
8. Configurar monitoramento HTTPS/`/up`, alarmes, e-mails e recuperação em caso de falha.
9. Executar o Composer Audit e suíte de regressão. Revisar PHP/extensões e atualizações antes do lançamento.
10. Estabelecer plano reversível de deploy e janela de manutenção. Testar migrations **em uma cópia da base**, nunca executar `migrate:fresh`, `key:generate` ou restauração sobre a base principal.

## Implantação (orientação, NÃO automatizada neste pacote)

Escolha hospedagem compatível com Laravel, PHP e banco gerenciado; publique apenas o diretório `public` como document root, configure HTTPS e aplique as instruções oficiais de deploy do Laravel (`php artisan optimize`). O ambiente WSL/Sail de desenvolvimento **não deve ser exposto como servidor de produção**. Configure o serviço de filas/scheduler somente após validar a arquitetura do host e as responsabilidades dos operadores.

Fontes oficiais:
- https://laravel.com/docs/13.x/deployment
- https://laravel.com/docs/13.x/artisan
