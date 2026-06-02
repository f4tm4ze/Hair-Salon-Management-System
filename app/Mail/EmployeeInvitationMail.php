<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $jobTitle;
    public $acceptUrl;

    public function __construct(User $user, $jobTitle)
    {
        $this->user = $user;
        $this->jobTitle = $jobTitle;
        $this->acceptUrl = route('invitation.accept', $user->invitation_token);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitation to join ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.employee-invitation',
        );
    }
}
