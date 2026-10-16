<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🛒 New Order #RA-' . $this->order->id . ' — $' . number_format($this->order->total_amount, 2),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-new-order',
            with: ['order' => $this->order],
        );
    }
}