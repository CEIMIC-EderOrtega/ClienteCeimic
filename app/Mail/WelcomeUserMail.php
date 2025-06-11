<?php

namespace App\Mail;

use App\Models\User; // Importa el modelo User
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeUserMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * La instancia del usuario.
     *
     * @var \App\Models\User
     */
    public $user; // Hacemos la variable pública para que sea accesible en la vista

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        // Al crear una instancia de este correo, pasamos el objeto del nuevo usuario
        $this->user = $user;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Bienvenido a ' . config('app.name') . '!', // Asunto del correo
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Le decimos que use una plantilla Blade para el contenido del correo
        return new Content(
            markdown: 'emails.welcome', // Apunta a resources/views/emails/welcome.blade.php
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
