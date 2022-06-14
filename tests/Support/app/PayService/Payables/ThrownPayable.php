<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables;

use Exception;
use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Contracts\Payable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ThrownPayable implements Payable, ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function execute()
    {
        throw new Exception("They've done it! They've raised $50,000 for Frank's conveniently priced surgery!");
    }

    public function failed($error)
    {
        test()->expect($error->getMessage())->toBe("They've done it! They've raised $50,000 for Frank's conveniently priced surgery!");
    }
}
