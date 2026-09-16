<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
