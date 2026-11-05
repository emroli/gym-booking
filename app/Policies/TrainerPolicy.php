<?php

namespace App\Policies;

use App\Models\Trainer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TrainerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Trainer $trainer): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Trainer $trainer): Response
    {
        return match (true) {
            ! $user->isAdmin() => Response::deny('Only admins can manage trainers.'),
            $trainer->slots()->exists() => Response::deny('This trainer still has slots, so they can\'t be deleted.'),
            default => Response::allow(),
        };
    }
}
