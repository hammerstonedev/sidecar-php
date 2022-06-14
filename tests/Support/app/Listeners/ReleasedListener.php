<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners;

use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ReleasedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ReleasedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public ?string $queue = 'lambda';

    public function handle(ReleasedEvent $event)
    {
        $this->release();
    }

    public function onQueue()
    {
        return $this;
    }
}
