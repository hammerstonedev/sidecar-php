<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications;

use Facades\Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\ImplementsDoNotRunInLambdaMailable;
use Hammerstone\Sidecar\PHP\Contracts\Queue\DoNotRunInLambda;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ImplementsDoNotRunInLambdaNotification extends Notification implements ShouldQueue, DoNotRunInLambda
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return ImplementsDoNotRunInLambdaMailable::to($notifiable?->routes['mail'] ?? $notifiable?->email ?? $notifiable?->email);
    }
}
