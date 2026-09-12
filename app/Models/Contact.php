<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    public const TYPES = ['customer' => 'Customers', 'supplier' => 'Suppliers', 'commission' => 'Commission'];

    public const FORM_TYPES = self::TYPES + ['both' => 'Customer & Supplier'];

    protected $fillable = [
        'type', 'contact_id', 'entity_type', 'name', 'business_name', 'customer_group',
        'mobile', 'alternate_number', 'landline', 'email', 'assigned_to', 'status',
        'tax_number', 'opening_balance', 'pay_term', 'pay_term_unit', 'credit_limit',
        'opening_due_cans', 'commission_percentage', 'address_line_1', 'address_line_2',
        'city', 'state', 'country', 'zip_code', 'custom_fields', 'shipping_address', 'date_of_birth',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array', 'last_sale_at' => 'date', 'date_of_birth' => 'date:Y-m-d',
            'opening_balance' => 'decimal:2', 'advance_balance' => 'decimal:2',
            'due_balance' => 'decimal:2', 'return_balance' => 'decimal:2',
            'credit_limit' => 'decimal:2', 'commission_percentage' => 'decimal:2',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
