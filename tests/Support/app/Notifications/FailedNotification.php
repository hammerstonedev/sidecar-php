<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications;

use Facades\Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\FailedMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FailedNotification extends Notification implements ShouldQueue
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
        return FailedMailable::to($notifiable?->routes['mail'] ?? $notifiable?->email ?? $notifiable?->email);
    }

    public function failed($error)
    {
        test()->expect($error->getMessage())->toBe("I'm just kidding. I could hear you. It was just really mean.");
    }
}
