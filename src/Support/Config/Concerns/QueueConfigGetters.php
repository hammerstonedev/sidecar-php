<?php

namespace Hammerstone\Sidecar\PHP\Support\Config\Concerns;

use Illuminate\Support\Facades\Queue;

trait QueueConfigGetters
{
    public function queueFeatureEnabled(): bool
    {
        return (bool) config('sidecar.queue.enabled');
    }

    public function queueFeatureDisabled(): bool
    {
        return $this->queueFeatureEnabled() === false;
    }

    public function shouldBindSidecarQueueWorker(): bool
    {
        return $this->queueFeatureEnabled()
            && $this->queueDriverSupported();
    }

    public function queueDriverSupported(): bool
    {
        return $this->queueDriverNotSupported() === false;
    }

    public function queueDriverNotSupported(): bool
    {
        $driver = Queue::getDefaultDriver() ?? 'null';
        $notSupported = in_array($driver, ['sync', 'null']);

        if ($notSupported && app()->runningUnitTests()) {
            test()->markTestSkipped("The [{$driver}] queue driver is not supported by sidecar.");
        }

        return $notSupported;
    }
}
