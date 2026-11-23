<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Password reset mail that links to the frontend instead of a Blade route.
 */
class ResetPassword extends BaseResetPassword
{
    protected function resetUrl(mixed $notifiable): string
    {
        $query = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.$query;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('auth.reset_subject'))
            ->line(__('auth.reset_intro'))
            ->action(__('auth.reset_action'), $this->resetUrl($notifiable))
            ->line(__('auth.reset_expiry', ['count' => $this->expireMinutes()]))
            ->line(__('auth.reset_ignore'));
    }

    private function expireMinutes(): int
    {
        return (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
    }
}
