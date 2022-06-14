<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ThrownJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        throw new Exception("You're a terrible stuntman.");
    }

    public function failed($error)
    {
        test()->expect($error->getMessage())->toBe("You're a terrible stuntman.");
    }
}
