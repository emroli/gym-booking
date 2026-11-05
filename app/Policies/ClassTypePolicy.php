<?php

namespace App\Policies;

use App\Models\ClassType;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ClassTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ClassType $classType): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ClassType $classType): Response
    {
        return match (true) {
            ! $user->isAdmin() => Response::deny('Only admins can manage class types.'),
            $classType->slots()->exists() => Response::deny('This class type still has slots, so it can\'t be deleted.'),
            default => Response::allow(),
        };
    }
}
