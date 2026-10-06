<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryConsignmentProof extends Model
{
    public $timestamps = false;
    protected $fillable = ['kind', 'path', 'uploaded_by', 'created_at'];
}
