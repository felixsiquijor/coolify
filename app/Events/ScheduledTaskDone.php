<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScheduledTaskDone implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $teamId;

    public function __construct($teamId = null)
    {
        if (is_null($teamId)) {
            $teamId = auth()->user()->currentTeam()->id ?? null;
        }
        if (is_null($teamId)) {
            throw new \Exception('Team id is null');
        }
        $this->teamId = $teamId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("team.{$this->teamId}"),
        ];
    }
}
