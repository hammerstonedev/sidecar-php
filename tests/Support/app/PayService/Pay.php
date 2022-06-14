<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\PayService;

use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Contracts\Payable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Jobs\SendQueuedPayable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class Pay implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Payable $payable;

    public function getPayable(): Payable
    {
        return $this->payable;
    }

    public function send(Payable $payable): void
    {
        $this->payable = $payable;

        $this->newQueuedJob()->handle();
    }

    public function queue(Payable $payable)
    {
        if (isset($payable->delay)) {
            return $this->later($payable->delay, $payable);
        }

        $this->payable = $payable;

        $connection = property_exists($this->payable, 'connection') ? $this->payable->connection : null;
        $queueName = property_exists($this->payable, 'queue') ? $this->payable->queue : null;

        return app()->make('queue')->connection($connection)->pushOn($queueName ?: null, $this->newQueuedJob());
    }

    public function later($delay, Payable $payable)
    {
        $this->payable = $payable;

        $connection = property_exists($this->payable, 'connection') ? $this->payable->connection : null;
        $queueName = property_exists($this->payable, 'queue') ? $this->payable->queue : null;

        return app()->make('queue')->connection($connection)->laterOn($queueName ?: null, $delay, $this->newQueuedJob());
    }

    protected function newQueuedJob(): SendQueuedPayable
    {
        return (new SendQueuedPayable($this->payable))->through(array_merge(
            method_exists($this->payable, 'middleware') ? $this->payable->middleware() : [],
            $this->payable->middleware ?? [],
        ));
    }
}
