<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FailedEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
}
