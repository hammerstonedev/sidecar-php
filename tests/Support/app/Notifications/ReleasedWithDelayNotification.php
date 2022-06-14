<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications;

use Facades\Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\ReleasedWithDelayMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReleasedWithDelayNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        test()->markTestSkipped('Notifications cannot interact with the queue.');
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return ReleasedWithDelayMailable::to($notifiable?->routes['mail'] ?? $notifiable?->email ?? $notifiable?->email);
    }
}
