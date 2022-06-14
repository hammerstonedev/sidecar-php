<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Mail;

use Hammerstone\Sidecar\PHP\Contracts\Queue\RunInLambda;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReleasedWithDelayMailable extends Mailable implements ShouldQueue, RunInLambda
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        test()->markTestSkipped('Mailables cannot interact with the queue.');
    }

    public function build()
    {
        return $this->view('email', ['content' => "No, Kevin, I know, for a fact, you don't party."]);
    }
}
