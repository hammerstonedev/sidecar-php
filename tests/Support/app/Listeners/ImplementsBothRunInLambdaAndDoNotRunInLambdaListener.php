<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners;

use Hammerstone\Sidecar\PHP\Contracts\Queue\DoNotRunInLambda;
use Hammerstone\Sidecar\PHP\Contracts\Queue\RunInLambda;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ImplementsBothRunInLambdaAndDoNotRunInLambdaEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ImplementsBothRunInLambdaAndDoNotRunInLambdaListener implements ShouldQueue, RunInLambda, DoNotRunInLambda
{
    use InteractsWithQueue;

    public ?string $queue = 'lambda';

    public function handle(ImplementsBothRunInLambdaAndDoNotRunInLambdaEvent $event)
    {
    }

    public function onQueue()
    {
        return $this;
    }
}
