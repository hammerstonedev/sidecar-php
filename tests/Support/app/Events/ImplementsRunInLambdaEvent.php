<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Events;

use Hammerstone\Sidecar\PHP\Contracts\Queue\RunInLambda;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImplementsRunInLambdaEvent implements ShouldQueue, RunInLambda
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
}
