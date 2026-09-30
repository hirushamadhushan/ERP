<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    /** Create all parts of a user atomically so permissions cannot be partially saved. */
    public function create(array $data): User
    {
        $limit = filter_var(config('erp.user_limit'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if ($limit !== null && User::count() >= $limit) {
            throw ValidationException::withMessages(['email' => 'The user limit for this subscription has been reached.']);
        }

        return DB::transaction(function () use ($data) {
            $user = User::create($this->userAttributes($data, true));
            $this->syncRelatedData($user, $data);
            $user->activities()->create([
                'actor_id' => auth()->id(),
                'action' => 'User account created',
                'note' => 'User profile and access settings were created.',
            ]);

            return $user->load(['assignedRole', 'profile', 'locations', 'selectedContacts']);
        });
    }

    /** Update the identity, profile and access rules in one transaction. */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->update($this->userAttributes($data, false));
            $this->syncRelatedData($user, $data);
            $user->activities()->create([
                'actor_id' => auth()->id(),
                'action' => 'User profile updated',
                'note' => 'User profile or access settings were updated.',
            ]);

            return $user->refresh()->load(['assignedRole', 'profile', 'locations', 'selectedContacts']);
        });
    }

    private function userAttributes(array $data, bool $creating): array
    {
        $allowLogin = (bool) ($data['allow_login'] ?? false);
        $name = trim(implode(' ', array_filter([$data['prefix'] ?? null, $data['first_name'], $data['last_name'] ?? null])));
        $attributes = Arr::only($data, ['prefix', 'first_name', 'last_name', 'email', 'status',
            'commission_percent', 'max_sales_discount_percent']);
        $attributes += [
            'name' => $name,
            'role_id' => Role::whereKey($data['role_id'])->value('id'),
            'allow_login' => $allowLogin,
            'all_locations' => (bool) ($data['all_locations'] ?? false),
            'restrict_contacts' => (bool) ($data['restrict_contacts'] ?? false),
            'username' => $allowLogin ? ($data['username'] ?: $this->uniqueUsername($data['first_name'])) : null,
        ];

        if (! empty($data['password'])) {
            $attributes['password'] = Hash::make($data['password']);
        } elseif ($creating) {
            // The legacy schema requires a hash even when interactive login is disabled.
            $attributes['password'] = Hash::make(Str::random(64));
        }

        return $attributes;
    }

    private function syncRelatedData(User $user, array $data): void
    {
        $profileFields = ['date_of_birth', 'gender', 'marital_status', 'blood_group', 'mobile',
            'alternate_contact', 'family_contact', 'facebook_link', 'twitter_link', 'social_media_1',
            'social_media_2', 'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4',
            'guardian_name', 'id_proof_name', 'id_proof_number', 'permanent_address', 'current_address',
            'account_holder_name', 'account_number', 'bank_name', 'bank_identifier_code', 'bank_branch', 'tax_payer_id'];
        $user->profile()->updateOrCreate([], Arr::only($data, $profileFields));
        $user->locations()->sync($user->all_locations ? [] : ($data['location_ids'] ?? []));
        $user->selectedContacts()->sync($user->restrict_contacts ? ($data['contact_ids'] ?? []) : []);
    }

    private function uniqueUsername(string $firstName): string
    {
        $base = Str::lower(Str::slug($firstName, '.')) ?: 'user';
        $candidate = $base;
        for ($suffix = 1; User::where('username', $candidate)->exists(); $suffix++) {
            $candidate = $base.'.'.$suffix;
        }

        return $candidate;
    }
}
