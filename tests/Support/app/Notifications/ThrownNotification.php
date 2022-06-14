<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications;

use Facades\Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\ThrownMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ThrownNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return ThrownMailable::to($notifiable?->routes['mail'] ?? $notifiable?->email ?? $notifiable?->email);
    }

    public function failed($error)
    {
        test()->expect($error->getMessage())->toBe("I'm just kidding. I could hear you. It was just really mean.");
    }
}
