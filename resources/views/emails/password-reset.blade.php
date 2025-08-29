<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Redefinição de Senha</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height:1.6; color:#111;">
    <h2>Redefinição de senha</h2>
    <p>Recebemos uma solicitação para redefinir sua senha. Se foi você, clique no link abaixo para continuar:</p>
    <p>
        <a href="{{ $resetUrl }}" style="display:inline-block; padding:10px 16px; background:#2563eb; color:#fff; text-decoration:none; border-radius:6px;">Alterar minha senha</a>
    </p>
    <p>Ou copie e cole este link no navegador:</p>
    <p><a href="{{ $resetUrl }}">{{ $resetUrl }}</a></p>
    <p>Se você não solicitou, ignore este e-mail. O link expira em breve.</p>
    <p>Obrigado.</p>
</body>
<!-- Este e-mail é informativo e não deve ser respondido. -->
</html>


