<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Warranty extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'duration',
        'duration_type',
    ];

    public function getFormattedDurationAttribute(): string
    {
        return $this->duration . ' ' . ucfirst($this->duration_type);
    }

    public function getEndDate(mixed $date): string
    {
        $startDate = Carbon::parse($date);

        return (match ($this->duration_type) {
            'days' => $startDate->addDays($this->duration),
            'months' => $startDate->addMonths($this->duration),
            'years' => $startDate->addYears($this->duration),
        })->toDateTimeString();
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
