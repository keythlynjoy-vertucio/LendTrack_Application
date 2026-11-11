<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class PaymentRecorded extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $paidAt = $this->payment->paid_at;

        return [
            'payment_id' => $this->payment->id,
            'loan_id' => $this->payment->loan_id,
            'amount' => (float) $this->payment->amount,
            'paid_at' => $paidAt instanceof \DateTimeInterface ? $paidAt->format('Y-m-d') : null,
            'message' => 'A payment has been recorded for loan '.$this->payment->loan->reference_no.'.',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
