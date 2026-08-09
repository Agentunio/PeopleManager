<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Zmiana hasła do konta',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-requested',
            with: [
                'username' => $this->user->username,
                'resetUrl' => route('password.reset', [
                    'token' => $this->token,
                    'email' => $this->user->email,
                ]),
                'expiresIn' => config('auth.passwords.users.expire'),
            ],
        );
    }
}
