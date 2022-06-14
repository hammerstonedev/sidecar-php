<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners;

use Hammerstone\Sidecar\PHP\Contracts\Queue\RunInLambda;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ImplementsRunInLambdaEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ImplementsRunInLambdaListener implements ShouldQueue, RunInLambda
{
    use InteractsWithQueue;

    public ?string $queue = 'lambda';

    public function handle(ImplementsRunInLambdaEvent $event)
    {
    }

    public function onQueue()
    {
        return $this;
    }
}
