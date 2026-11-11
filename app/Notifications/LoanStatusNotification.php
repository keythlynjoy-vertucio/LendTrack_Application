<?php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class LoanStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Loan $loan,
        public string $action,
        public User $performedBy
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $borrowerName = $this->loan->borrower
            ? ($this->loan->borrower->last_name . ', ' . $this->loan->borrower->first_name)
            : 'Unknown borrower';

        $actionLabel = $this->action === 'closed' ? 'was closed' : 'was reopened';
        $message = "Loan {$this->loan->reference_no} for {$borrowerName} {$actionLabel} by {$this->performedBy->name}.";

        return [
            'loan_id' => $this->loan->id,
            'loan_reference' => $this->loan->reference_no,
            'borrower_name' => $borrowerName,
            'action' => $this->action,
            'performed_by' => $this->performedBy->name,
            'performed_by_id' => $this->performedBy->id,
            'message' => $message,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
