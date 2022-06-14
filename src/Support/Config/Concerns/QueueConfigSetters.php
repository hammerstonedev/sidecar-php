<?php

namespace Hammerstone\Sidecar\PHP\Support\Config\Concerns;

use Hammerstone\Sidecar\PHP\LaravelLambda;

trait QueueConfigSetters
{
    public function enableQueueFeature(bool $optin = true, string|array $queues = '*'): self
    {
        config([
            'sidecar.queue.enabled' => true,
            'sidecar.queue.allowed_queues' => $queues,
            'sidecar.queue.opt_in_required' => $optin,
            'sidecar.functions' => array_unique(array_merge(config('sidecar.functions', []), [
                LaravelLambda::class,
            ])),
        ]);

        return $this->resetSingletons();
    }

    public function disableQueueFeature(): self
    {
        config(['sidecar.queue.enabled' => false]);

        return $this->resetSingletons();
    }

    private function resetSingletons(): self
    {
        app()->forgetInstance('command.queue.work');

        return $this;
    }
}
