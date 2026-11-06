# Recuperação segura de acesso administrativo (API)

Esta etapa adiciona o comando **opcional** `mf:admin-acesso`, executado somente no terminal da máquina onde a API está rodando. Ele **não** adiciona um botão de recuperação pública nem uma conta com senha padrão. Um usuário com acesso ao servidor e banco deve autorizar explicitamente a criação/recuperação.

## Consultar ou recuperar

```bash
cd ~/projetos/mf-avaliacao-api
./vendor/bin/sail artisan mf:admin-acesso
```

1. Confirme o nome do **banco conectado** mostrado no terminal. Se for o banco errado, **cancele**.
2. Se não existir administrador, o comando permite criar o primeiro, solicitando nome, e-mail e senha de forma interativa.
3. Se houver administradores, lista seus IDs e e-mails; pode redefinir somente a senha de uma conta administrativa **ativa**, mediante confirmação adicional.
4. A senha não aparece na saída e **não pode ser fornecida por parâmetro ou execução não interativa**. Ela deve ter de 12 a 200 caracteres e conter letras e números.
5. Após uma redefinição, **todos os tokens Sanctum da conta são revogados**, exigindo novo login nos aparelhos.

Não use `tinker` com senhas expostas em literais. Não reutilize senhas simples. Guarde a senha no seu gerenciador de senhas. Se o login falhar logo após tentativas incorretas, verifique também o bloqueio temporário de tentativas de autenticação.

Este comando não altera alunos, avaliações, fotografias, GPG, backups ou APP_KEY. Não é necessário executá-lo se o login já funciona.
