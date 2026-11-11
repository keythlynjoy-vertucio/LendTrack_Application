<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'borrower_id',
        'user_id',
        'reference_no',
        'principal_amount',
        'interest_rate',
        'term_months',
        'frequency',
        'start_date',
        'first_due_date',
        'due_date',
        'penalty_per_missed_due',
        'notes',
        'total_payable',
        'total_paid',
        'status',
        'remarks',
        'closed_at',
        'closed_by',
        'close_reason',
        'close_remarks',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'penalty_per_missed_due' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'start_date' => 'date',
        'first_due_date' => 'date',
        'due_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getOutstandingBalanceAttribute(): float
    {
        return round((float) $this->total_payable - (float) $this->total_paid, 2);
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status ?? 'pending');
    }

    public function getCloseReasonLabelAttribute(): ?string
    {
        $reasons = [
            'fully_paid_not_updated' => 'Fully Paid but Not Auto Updated',
            'settled_outside' => 'Settled Outside the System',
            'cancelled_terminated' => 'Cancelled or Terminated',
            'written_off' => 'Written Off',
        ];
        
        return $reasons[$this->close_reason] ?? null;
    }

    public function canBeClosed(): bool
    {
        return $this->status !== 'paid';
    }
}
