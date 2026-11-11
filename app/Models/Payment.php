<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'loan_id',
        'user_id',
        'amount',
        'penalty_paid',
        'paid_at',
        'method',
        'reference_no',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'penalty_paid' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
