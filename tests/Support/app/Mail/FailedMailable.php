<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FailedMailable extends Mailable implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        test()->markTestSkipped('Mailables cannot interact with the queue.');
    }

    public function build()
    {
        $this->fail();
    }

    public function failed($error)
    {
    }
}
