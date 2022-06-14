<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications;

use Facades\Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\PassedMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PassedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return PassedMailable::to($notifiable?->routes['mail'] ?? $notifiable?->email ?? $notifiable?->email);
    }
}
