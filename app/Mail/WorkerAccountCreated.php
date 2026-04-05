<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkerAccountCreated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Twoje konto w sortowni Orlen Paczka Toruń czeka na aktywację',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.worker-account-created',
            with: [
                'firstName' => $this->user->worker->first_name,
                'username' => $this->user->username,
                'activationUrl' => route('account.activate', $this->token),
            ],
        );
    }
}
