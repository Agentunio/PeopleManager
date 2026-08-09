<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkerAccountActivated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Twoje konto zostało aktywowane',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.worker-account-activated',
            with: [
                'firstName' => $this->user->worker->first_name,
                'username' => $this->user->username,
                'loginUrl' => route('login'),
            ],
        );
    }
}
