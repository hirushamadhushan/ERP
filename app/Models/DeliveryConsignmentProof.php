<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryConsignmentProof extends Model
{
    public $timestamps = false;
    protected $fillable = ['kind', 'path', 'caption', 'checksum', 'retention_until', 'uploaded_by', 'created_at', 'deleted_at', 'deleted_by', 'purged_at'];
    protected function casts(): array { return ['created_at' => 'datetime', 'retention_until' => 'datetime', 'deleted_at' => 'datetime', 'purged_at' => 'datetime']; }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function deletedBy() { return $this->belongsTo(User::class, 'deleted_by'); }
}
