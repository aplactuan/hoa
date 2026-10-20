<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SetPassword extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable instanceof CanResetPassword
            ? $notifiable->getEmailForPasswordReset()
            : '';

        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $email,
        ], false));

        return (new MailMessage)
            ->subject(__('Set your :name password', ['name' => config('app.name')]))
            ->line(__('An administrator created an account for you.'))
            ->action(__('Set password'), $url)
            ->line(__('If you were not expecting this, you can ignore this email.'));
    }
}
