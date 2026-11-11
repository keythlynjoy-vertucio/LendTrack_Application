<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

class Borrower extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'address',
        'status',
        'notes',
        'profile_photo_path',
        'region_code',
        'region_name',
        'province_code',
        'province_name',
        'city_municipality_code',
        'city_municipality_name',
        'barangay_code',
        'barangay_name',
        'street_house',
    ];

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Loan::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path ? asset('storage/'.$this->profile_photo_path) : null;
    }

    public function getInitialsAttribute(): string
    {
        $name = trim($this->first_name.' '.$this->last_name);
        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1).substr(end($parts), 0, 1));
        }

        return strtoupper(substr($parts[0] ?? '', 0, 1));
    }
}
