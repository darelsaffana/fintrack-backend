<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Kirim kode reset sebagai teks polos, bukan link, karena app ini
     * tidak punya route web untuk ditautkan (API + Flutter murni) —
     * pengguna memasukkan kode ini manual di halaman reset password.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode Reset Password Fintrack')
            ->line('Anda meminta reset password untuk akun Fintrack Anda.')
            ->line('Kode reset Anda:')
            ->line($this->token)
            ->line('Kode ini berlaku selama 60 menit. Abaikan email ini jika Anda tidak meminta reset password.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
