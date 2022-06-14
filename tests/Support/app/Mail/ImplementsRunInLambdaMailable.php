<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Mail;

use Hammerstone\Sidecar\PHP\Contracts\Queue\RunInLambda;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImplementsRunInLambdaMailable extends Mailable implements ShouldQueue, RunInLambda
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function build()
    {
        return $this->view('email', ['content' => 'Cool beans!']);
    }
}
