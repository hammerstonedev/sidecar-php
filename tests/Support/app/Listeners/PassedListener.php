<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners;

use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\PassedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class PassedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public ?string $queue = 'lambda';

    public function handle(PassedEvent $event)
    {
    }

    public function onQueue()
    {
        return $this;
    }
}
