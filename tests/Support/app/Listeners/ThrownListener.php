<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners;

use Exception;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ThrownEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ThrownListener implements ShouldQueue
{
    use InteractsWithQueue;

    public ?string $queue = 'lambda';

    public function handle(ThrownEvent $event)
    {
        throw new Exception('A cooked goose for everyone!');
    }

    public function failed($error)
    {
        test()->expect($error->getMessage())->toBe('A cooked goose for everyone!');
    }

    public function onQueue()
    {
        return $this;
    }
}
