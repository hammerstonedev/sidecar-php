<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Mail;

use Hammerstone\Sidecar\PHP\Contracts\Queue\DoNotRunInLambda;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImplementsDoNotRunInLambdaMailable extends Mailable implements ShouldQueue, DoNotRunInLambda
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function build()
    {
        return $this->view('email', ['content' => 'Hwhy ham hwi saying hwhat hwhat hway?']);
    }
}
