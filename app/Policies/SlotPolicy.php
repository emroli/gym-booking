<?php

namespace App\Policies;

use App\Models\Slot;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SlotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function viewAttendees(User $user, Slot $slot): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Slot $slot): Response
    {
        return $this->changeable($user, $slot);
    }

    public function cancel(User $user, Slot $slot): Response
    {
        return $this->changeable($user, $slot);
    }

    private function changeable(User $user, Slot $slot): Response
    {
        return match (true) {
            ! $user->isAdmin() => Response::deny('Only admins can manage the schedule.'),
            $slot->isCancelled() => Response::deny('This class has been cancelled.'),
            $slot->hasStarted() => Response::deny('This class has already started.'),
            default => Response::allow(),
        };
    }
}
