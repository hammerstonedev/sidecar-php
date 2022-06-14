<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Events;

use Hammerstone\Sidecar\PHP\Contracts\Queue\DoNotRunInLambda;
use Hammerstone\Sidecar\PHP\Contracts\Queue\RunInLambda;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImplementsBothRunInLambdaAndDoNotRunInLambdaEvent implements ShouldQueue, RunInLambda, DoNotRunInLambda
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
}
