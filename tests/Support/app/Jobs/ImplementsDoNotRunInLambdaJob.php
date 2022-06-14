<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs;

use Hammerstone\Sidecar\PHP\Contracts\Queue\DoNotRunInLambda;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImplementsDoNotRunInLambdaJob implements ShouldQueue, DoNotRunInLambda
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
    }
}
