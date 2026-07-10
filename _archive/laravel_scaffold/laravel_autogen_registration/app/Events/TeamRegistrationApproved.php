<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamRegistrationApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $registrationId;

    public function __construct(int $registrationId)
    {
        $this->registrationId = $registrationId;
    }
}
