<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications;

use Facades\Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\ImplementsBothRunInLambdaAndDoNotRunInLambdaMailable;
use Hammerstone\Sidecar\PHP\Contracts\Queue\DoNotRunInLambda;
use Hammerstone\Sidecar\PHP\Contracts\Queue\RunInLambda;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ImplementsBothRunInLambdaAndDoNotRunInLambdaNotification extends Notification implements ShouldQueue, RunInLambda, DoNotRunInLambda
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return ImplementsBothRunInLambdaAndDoNotRunInLambdaMailable::to($notifiable?->routes['mail'] ?? $notifiable?->email ?? $notifiable?->email);
    }
}
