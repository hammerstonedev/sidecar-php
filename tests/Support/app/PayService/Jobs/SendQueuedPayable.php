<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Jobs;

use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Contracts\Payable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Throwable;

class SendQueuedPayable
{
    use Queueable;

    public Payable $payable;
    public ?int $tries;
    public ?int $timeout;
    public bool $shouldBeEncrypted = false;

    public function __construct(Payable $payable)
    {
        $this->payable = $payable;
        $this->tries = property_exists($payable, 'tries') ? $payable->tries : null;
        $this->timeout = property_exists($payable, 'timeout') ? $payable->timeout : null;
        $this->afterCommit = property_exists($payable, 'afterCommit') ? $payable->afterCommit : null;
        $this->shouldBeEncrypted = $payable instanceof ShouldBeEncrypted;
    }

    public function handle(): void
    {
        $this->payable->execute();
    }

    public function displayName(): string
    {
        return get_class($this->payable);
    }

    public function failed(Throwable $error): void
    {
        if (method_exists($this->payable, 'failed')) {
            $this->payable->failed($error);
        }
    }

    public function backoff(): ?int
    {
        if (! method_exists($this->payable, 'backoff') && ! isset($this->payable->backoff)) {
            return null;
        }

        return $this->payable->backoff ?? $this->payable->backoff();
    }

    public function __clone()
    {
        $this->payable = clone $this->payable;
    }
}
