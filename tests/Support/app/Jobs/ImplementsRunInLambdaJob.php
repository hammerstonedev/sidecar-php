<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs;

use Hammerstone\Sidecar\PHP\Contracts\Queue\RunInLambda;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImplementsRunInLambdaJob implements ShouldQueue, RunInLambda
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
    }
}
