<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners;

use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\FailedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class FailedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public ?string $queue = 'lambda';

    public function handle(FailedEvent $event)
    {
        $this->fail();
    }

    public function onQueue()
    {
        return $this;
    }
}
