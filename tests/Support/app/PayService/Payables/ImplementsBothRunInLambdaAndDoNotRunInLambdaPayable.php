<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables;

use Hammerstone\Sidecar\PHP\Contracts\Queue\DoNotRunInLambda;
use Hammerstone\Sidecar\PHP\Contracts\Queue\RunInLambda;
use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Contracts\Payable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImplementsBothRunInLambdaAndDoNotRunInLambdaPayable implements Payable, ShouldQueue, RunInLambda, DoNotRunInLambda
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function execute()
    {
    }
}
