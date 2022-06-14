<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Events;

use Hammerstone\Sidecar\PHP\Contracts\Queue\DoNotRunInLambda;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImplementsDoNotRunInLambdaEvent implements ShouldQueue, DoNotRunInLambda
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
}
