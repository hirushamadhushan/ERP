<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'prefix',
        'first_name',
        'last_name',
        'name',
        'email',
        'password',
        'role_id',
        'status',
        'allow_login',
        'all_locations',
        'restrict_contacts',
        'commission_percent',
        'max_sales_discount_percent',
    ];

    protected $appends = ['role'];

    public function assignedRole()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class);
    }

    public function selectedContacts()
    {
        return $this->belongsToMany(Contact::class);
    }

    protected function role(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->assignedRole?->name,
            set: fn ($value) => ['role_id' => Role::whereRaw('LOWER(name) = ?', [strtolower((string) $value)])->value('id')],
        );
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'allow_login' => 'boolean',
            'all_locations' => 'boolean',
            'restrict_contacts' => 'boolean',
            'commission_percent' => 'decimal:2',
            'max_sales_discount_percent' => 'decimal:2',
        ];
    }
}
