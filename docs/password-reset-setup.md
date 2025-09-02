# Configuração do Fluxo de Redefinição de Senha

Fluxo hospedado em `contas.trocasenha.sei.pe.gov.br`.

## Variáveis de Ambiente

Adicione ao `.env`:

```env
# SMTP para envio de e-mails de redefinição (Produção)
MAIL_MAILER=smtp
MAIL_HOST=200.238.112.200
MAIL_PORT=25
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@sei.pe.gov.br"
MAIL_FROM_NAME="SEI Contas"

# Captcha (mews/captcha - local, sem dependências externas)
CAPTCHA_DISABLE=false
```

## DNS e Domínio

- Aponte `contas.trocasenha.sei.pe.gov.br` para a aplicação.
- Rotas definidas com `Route::domain('contas.trocasenha.sei.pe.gov.br')`.

## Migrações

```bash
php artisan migrate
# ou
./vendor/bin/sail artisan migrate
```

## Fluxo

1. `https://contas.trocasenha.sei.pe.gov.br/` → formulário de e-mail + captcha.
2. Envio gera token único (hash salvo, expira) e e-mail com link.
3. `https://contas.trocasenha.sei.pe.gov.br/{token}` → definir nova senha + captcha.
4. Senha atualizada no LDAP, token invalidado, tela de sucesso.

## Segurança

- Token expira (`PasswordResetService::EXPIRATION_MINUTES`).
- Token armazenado como hash (SHA-256).
- Invalidação após uso.
- Mensagens amigáveis sem revelar existência de usuário.
