<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PassedMailable extends Mailable implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function build()
    {
        return $this->view('email', ['content' => 'Cool beans.']);
    }
}
