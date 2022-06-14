<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Mail;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ThrownMailable extends Mailable implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function build()
    {
        throw new Exception("I'm just kidding. I could hear you. It was just really mean.");
    }

    public function failed($error)
    {
        test()->expect($error->getMessage())->toBe("I'm just kidding. I could hear you. It was just really mean.");
    }
}
