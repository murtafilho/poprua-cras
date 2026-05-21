# Configurar Recuperação de Senha - POPRUA

Este guia mostra como configurar o envio de emails para recuperação de senha no sistema POPRUA.

## ✅ O que já está implementado

O Laravel Breeze já possui toda a funcionalidade de recuperação de senha implementada:
- ✅ Rota `/forgot-password` para solicitar recuperação
- ✅ Rota `/reset-password/{token}` para redefinir senha
- ✅ Controllers: `PasswordResetLinkController` e `NewPasswordController`
- ✅ Views traduzidas para português
- ✅ Tabela `password_reset_tokens` no banco de dados

## 📧 Configuração do Email

### 1. Configurar o arquivo `.env`

Adicione as seguintes variáveis ao seu arquivo `.env`:

#### Opção 1: Mailtrap (Recomendado para desenvolvimento/testes)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=seu-username-mailtrap
MAIL_PASSWORD=sua-senha-mailtrap
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@poprua.com
MAIL_FROM_NAME="POPRUA"
```

**Como obter credenciais do Mailtrap:**
1. Acesse https://mailtrap.io
2. Crie uma conta gratuita
3. Crie um novo "Inbox"
4. Copie as credenciais SMTP (Username e Password)
5. Cole no arquivo `.env`

#### Opção 2: Gmail (Para produção)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=seu-email@gmail.com
MAIL_PASSWORD=sua-senha-de-app
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=seu-email@gmail.com
MAIL_FROM_NAME="POPRUA"
```

**Importante para Gmail:**
- Você precisa criar uma "Senha de App" no Google
- Não use sua senha normal do Gmail
- Acesse: https://myaccount.google.com/apppasswords

#### Opção 3: Log (Apenas para desenvolvimento local)

```env
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@poprua.com
MAIL_FROM_NAME="POPRUA"
```

Com esta opção, os emails não são enviados, mas são salvos em `storage/logs/laravel.log`.

### 2. Limpar cache de configuração

Após alterar o `.env`, execute:

```bash
php artisan config:clear
php artisan cache:clear
```

## 🧪 Como Testar

### 1. Acesse a página de recuperação de senha

```
http://localhost:8000/forgot-password
```

### 2. Digite um email cadastrado no sistema

### 3. Verifique o email

- **Mailtrap**: Acesse seu inbox no site do Mailtrap
- **Gmail**: Verifique a caixa de entrada (e spam)
- **Log**: Verifique `storage/logs/laravel.log`

### 4. Clique no link do email

O link terá o formato:
```
http://localhost:8000/reset-password/{token}?email=usuario@example.com
```

### 5. Defina uma nova senha

Preencha o formulário com a nova senha e confirmação.

## 📝 Personalizar o Email de Recuperação

O Laravel usa a notificação padrão `ResetPassword`. Para personalizar o template:

### Opção 1: Customizar via User Model (Recomendado)

Edite `app/Models/User.php` e adicione o método `sendPasswordResetNotification`:

```php
<?php

namespace App\Models;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class User extends Authenticatable
{
    // ... código existente ...

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token): void
    {
        $url = url(route('password.reset', [
            'token' => $token,
            'email' => $this->email,
        ], false));

        $this->notify(new ResetPassword($token));
    }
}
```

### Opção 2: Criar Notificação Customizada

1. Crie uma notificação customizada:
```bash
php artisan make:notification CustomResetPassword
```

2. Edite `app/Notifications/CustomResetPassword.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class CustomResetPassword extends Notification
{
    use Queueable;

    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
        ], false));

        return (new MailMessage)
            ->subject('Redefinir Senha - POPRUA')
            ->greeting('Olá!')
            ->line('Você está recebendo este email porque recebemos uma solicitação de redefinição de senha para sua conta.')
            ->action('Redefinir Senha', $url)
            ->line('Este link de redefinição de senha expirará em 60 minutos.')
            ->line('Se você não solicitou uma redefinição de senha, nenhuma ação adicional é necessária.')
            ->salutation('Atenciosamente, Equipe POPRUA');
    }
}
```

3. Atualize o User model:

```php
public function sendPasswordResetNotification($token): void
{
    $this->notify(new \App\Notifications\CustomResetPassword($token));
}
```

## 🔒 Segurança

- O token de redefinição expira em **60 minutos** (configurável em `config/auth.php`)
- O usuário pode solicitar um novo token a cada **60 segundos** (throttle)
- O token é único e não pode ser reutilizado
- Após redefinir a senha, o token anterior é invalidado

## 🐛 Troubleshooting

### Email não está sendo enviado

1. **Verifique as configurações do `.env`**
   ```bash
   php artisan config:clear
   ```

2. **Verifique os logs**
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Teste com Mailtrap primeiro**
   - É mais fácil de debugar
   - Não precisa configurar domínio
   - Verifica se o problema é no servidor SMTP

### Erro de autenticação SMTP

- **Gmail**: Use "Senha de App" ao invés da senha normal
- Verifique se o usuário e senha estão corretos
- Verifique se a porta e criptografia estão corretas (587 + TLS)

### Link de reset não funciona

- Verifique se o `APP_URL` no `.env` está correto
- O link deve ser acessível (não use `localhost` em produção)
- Verifique se o token não expirou (60 minutos)

### Email vai para spam

- Configure SPF e DKIM no seu domínio
- Use um serviço profissional (Mailgun, SendGrid, etc.)
- Evite palavras que ativam filtros de spam

## 📚 Recursos Adicionais

- [Documentação Laravel Mail](https://laravel.com/docs/mail)
- [Documentação Laravel Password Reset](https://laravel.com/docs/authentication#password-reset)
- [Mailtrap - Teste de Emails](https://mailtrap.io)
- [Google App Passwords](https://support.google.com/accounts/answer/185833)

## ✅ Checklist de Configuração

- [ ] Configurar variáveis de email no `.env`
- [ ] Limpar cache (`php artisan config:clear`)
- [ ] Testar envio de email
- [ ] Verificar se o link de reset funciona
- [ ] Personalizar template (opcional)
- [ ] Configurar domínio para produção (SPF/DKIM)
