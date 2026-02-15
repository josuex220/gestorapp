<?php

namespace App\Policies;

use App\Models\Charge;
use App\Models\User;

class ChargePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Charge $charge): bool
    {
        return $user->id === $charge->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Charge $charge): bool
    {
        return $user->id === $charge->user_id;
    }

    public function delete(User $user, Charge $charge): bool
    {
        return $user->id === $charge->user_id && $charge->status !== 'paid';
    }
}
