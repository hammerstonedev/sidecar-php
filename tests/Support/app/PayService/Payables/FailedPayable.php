<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables;

use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Contracts\Payable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FailedPayable implements Payable, ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        test()->markTestSkipped('Pattern cannot interact with the queue.');
    }

    public function execute()
    {
    }
}
