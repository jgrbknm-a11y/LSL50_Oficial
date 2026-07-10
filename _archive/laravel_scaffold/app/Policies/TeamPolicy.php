<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Team;

class TeamPolicy
{
    public function viewAny(?User $user): bool { return true; }
    public function view(?User $user, Team $team): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, Team $team): bool { return true; }
    public function delete(User $user, Team $team): bool { return true; }
}
