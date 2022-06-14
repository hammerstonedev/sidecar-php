<?php

namespace Hammerstone\Sidecar\PHP\Tests\Datasets;

use Facades\Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Pay;
use Facades\Illuminate\Mail\PendingMail;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\FailedEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ImplementsBothRunInLambdaAndDoNotRunInLambdaEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ImplementsDoNotRunInLambdaEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ImplementsRunInLambdaEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\PassedEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ReleasedEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ReleasedWithDelayEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ThrownEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\FailedJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\ImplementsBothRunInLambdaAndDoNotRunInLambdaJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\ImplementsDoNotRunInLambdaJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\ImplementsRunInLambdaJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\PassedJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\ReleasedJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\ReleasedWithDelayJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\ThrownJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\FailedListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ImplementsBothRunInLambdaAndDoNotRunInLambdaListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ImplementsDoNotRunInLambdaListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ImplementsRunInLambdaListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\PassedListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ReleasedListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ReleasedWithDelayListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ThrownListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\FailedMailable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\ImplementsBothRunInLambdaAndDoNotRunInLambdaMailable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\ImplementsDoNotRunInLambdaMailable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\ImplementsRunInLambdaMailable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\PassedMailable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\ReleasedMailable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\ReleasedWithDelayMailable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\ThrownMailable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications\FailedNotification;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications\ImplementsBothRunInLambdaAndDoNotRunInLambdaNotification;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications\ImplementsDoNotRunInLambdaNotification;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications\ImplementsRunInLambdaNotification;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications\PassedNotification;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications\ReleasedNotification;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications\ReleasedWithDelayNotification;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications\ThrownNotification;
use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables\FailedPayable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables\ImplementsBothRunInLambdaAndDoNotRunInLambdaPayable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables\ImplementsDoNotRunInLambdaPayable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables\ImplementsRunInLambdaPayable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables\PassedPayable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables\ReleasedPayable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables\ReleasedWithDelayPayable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables\ThrownPayable;
use Hammerstone\Sidecar\PHP\Tests\Support\QueueTestHelper;
use Illuminate\Support\Facades\Notification;

$passed = [
    'job' => fn () => new QueueTestHelper(new PassedJob, fn ($queueable) => dispatch($queueable)),
    'event/listener' => fn () => new QueueTestHelper(new PassedListener, fn () => PassedEvent::dispatch()),
    'mailable' => fn () => new QueueTestHelper((new PassedMailable)->to('example@email.com'), fn ($queueable) => PendingMail::queue($queueable)),
    'notification' => fn () => new QueueTestHelper(new PassedNotification, fn ($queueable) => Notification::route('mail', 'example@email.com')->notify($queueable)),
    'custom service with method getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'getPayable']]);
        return new QueueTestHelper(new PassedPayable, fn ($queueable) => Pay::queue($queueable));
    },
    'custom service with attribute getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'payable']]);
        return new QueueTestHelper(new PassedPayable, fn ($queueable) => Pay::queue($queueable));
    },
];

$failed = [
    'failed job' => fn () => new QueueTestHelper(new FailedJob, fn ($queueable) => dispatch($queueable)),
    'failed event/listener' => fn () => new QueueTestHelper(new FailedListener, fn () => FailedEvent::dispatch()),
    'failed mailable' => fn () => new QueueTestHelper((new FailedMailable)->to('example@email.com'), fn ($queueable) => PendingMail::queue($queueable)),
    'failed notification' => fn () => new QueueTestHelper(new FailedNotification, fn ($queueable) => Notification::route('mail', 'example@email.com')->notify($queueable)),
    'failed custom service with method getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'getPayable']]);
        return new QueueTestHelper(new FailedPayable, fn ($queueable) => Pay::queue($queueable));
    },
    'failed custom service with attribute getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'payable']]);
        return new QueueTestHelper(new FailedPayable, fn ($queueable) => Pay::queue($queueable));
    },
];

$thrown = [
    'thrown job' => fn () => new QueueTestHelper(new ThrownJob, fn ($queueable) => dispatch($queueable)),
    'thrown event/listener' => fn () => new QueueTestHelper(new ThrownListener, fn () => ThrownEvent::dispatch()),
    'thrown mailable' => fn () => new QueueTestHelper((new ThrownMailable)->to('example@email.com'), fn ($queueable) => PendingMail::queue($queueable)),
    'thrown notification' => fn () => new QueueTestHelper(new ThrownNotification, fn ($queueable) => Notification::route('mail', 'example@email.com')->notify($queueable)),
    'thrown custom service with method getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'getPayable']]);
        return new QueueTestHelper(new ThrownPayable, fn ($queueable) => Pay::queue($queueable));
    },
    'thrown custom service with attribute getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'payable']]);
        return new QueueTestHelper(new ThrownPayable, fn ($queueable) => Pay::queue($queueable));
    },
];

$released = [
    'released job' => fn () => new QueueTestHelper(new ReleasedJob, fn ($queueable) => dispatch($queueable)),
    'released event/listener' => fn () => new QueueTestHelper(new ReleasedListener, fn () => ReleasedEvent::dispatch()),
    'released mailable' => fn () => new QueueTestHelper((new ReleasedMailable)->to('example@email.com'), fn ($queueable) => PendingMail::queue($queueable)),
    'released notification' => fn () => new QueueTestHelper(new ReleasedNotification, fn ($queueable) => Notification::route('mail', 'example@email.com')->notify($queueable)),
    'released custom service with method getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'getPayable']]);
        return new QueueTestHelper(new ReleasedPayable, fn ($queueable) => Pay::queue($queueable));
    },
    'released custom service with attribute getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'payable']]);
        return new QueueTestHelper(new ReleasedPayable, fn ($queueable) => Pay::queue($queueable));
    },
];

$releasedWithDelay = [
    'released with delay job' => fn () => new QueueTestHelper(new ReleasedWithDelayJob, fn ($queueable) => dispatch($queueable)),
    'released with delay event/listener' => fn () => new QueueTestHelper(new ReleasedWithDelayListener, fn () => ReleasedWithDelayEvent::dispatch()),
    'released with delay mailable' => fn () => new QueueTestHelper((new ReleasedWithDelayMailable)->to('example@email.com'), fn ($queueable) => PendingMail::queue($queueable)),
    'released with delay notification' => fn () => new QueueTestHelper(new ReleasedWithDelayNotification, fn ($queueable) => Notification::route('mail', 'example@email.com')->notify($queueable)),
    'released with delay custom service with method getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'getPayable']]);
        return new QueueTestHelper(new ReleasedWithDelayPayable, fn ($queueable) => Pay::queue($queueable));
    },
    'released with delay custom service with attribute getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'payable']]);
        return new QueueTestHelper(new ReleasedWithDelayPayable, fn ($queueable) => Pay::queue($queueable));
    },
];

$optsIn = [
    'opted-in job' => fn () => new QueueTestHelper(new ImplementsRunInLambdaJob, fn ($queueable) => dispatch($queueable)),
    'opted-in event/listener' => fn () => new QueueTestHelper(new ImplementsRunInLambdaListener, fn () => ImplementsRunInLambdaEvent::dispatch()),
    'opted-in mailable' => fn () => new QueueTestHelper((new ImplementsRunInLambdaMailable)->to('example@email.com'), fn ($queueable) => PendingMail::queue($queueable)),
    'opted-in notification' => fn () => new QueueTestHelper(new ImplementsRunInLambdaNotification, fn ($queueable) => Notification::route('mail', 'example@email.com')->notify($queueable)),
    'opted-in custom service with method getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'getPayable']]);
        return new QueueTestHelper(new ImplementsRunInLambdaPayable, fn ($queueable) => Pay::queue($queueable));
    },
    'opted-in custom service with attribute getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'payable']]);
        return new QueueTestHelper(new ImplementsRunInLambdaPayable, fn ($queueable) => Pay::queue($queueable));
    },
];

$optsOut = [
    'opted-out job' => fn () => new QueueTestHelper(new ImplementsDoNotRunInLambdaJob, fn ($queueable) => dispatch($queueable)),
    'opted-out event/listener' => fn () => new QueueTestHelper(new ImplementsDoNotRunInLambdaListener, fn () => ImplementsDoNotRunInLambdaEvent::dispatch()),
    'opted-out mailable' => fn () => new QueueTestHelper((new ImplementsDoNotRunInLambdaMailable)->to('example@email.com'), fn ($queueable) => PendingMail::queue($queueable)),
    'opted-out notification' => fn () => new QueueTestHelper(new ImplementsDoNotRunInLambdaNotification, fn ($queueable) => Notification::route('mail', 'example@email.com')->notify($queueable)),
    'opted-out custom service with method getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'getPayable']]);
        return new QueueTestHelper(new ImplementsDoNotRunInLambdaPayable, fn ($queueable) => Pay::queue($queueable));
    },
    'opted-out custom service with attribute getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'payable']]);
        return new QueueTestHelper(new ImplementsDoNotRunInLambdaPayable, fn ($queueable) => Pay::queue($queueable));
    },
];

$optsInAndOut = [
    'opted-in and opted-out job' => fn () => new QueueTestHelper(new ImplementsBothRunInLambdaAndDoNotRunInLambdaJob, fn ($queueable) => dispatch($queueable)),
    'opted-in and opted-out event/listener' => fn () => new QueueTestHelper(new ImplementsBothRunInLambdaAndDoNotRunInLambdaListener, fn () => ImplementsBothRunInLambdaAndDoNotRunInLambdaEvent::dispatch()),
    'opted-in and opted-out mailable' => fn () => new QueueTestHelper((new ImplementsBothRunInLambdaAndDoNotRunInLambdaMailable)->to('example@email.com'), fn ($queueable) => PendingMail::queue($queueable)),
    'opted-in and opted-out notification' => fn () => new QueueTestHelper(new ImplementsBothRunInLambdaAndDoNotRunInLambdaNotification, fn ($queueable) => Notification::route('mail', 'example@email.com')->notify($queueable)),
    'opted-in and opted-out custom service with method getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'getPayable']]);
        return new QueueTestHelper(new ImplementsBothRunInLambdaAndDoNotRunInLambdaPayable, fn ($queueable) => Pay::queue($queueable));
    },
    'opted-in and opted-out custom service with attribute getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'payable']]);
        return new QueueTestHelper(new ImplementsBothRunInLambdaAndDoNotRunInLambdaPayable, fn ($queueable) => Pay::queue($queueable));
    },
];

dataset('passed jobs', $passed);
dataset('failed jobs', $failed);
dataset('thrown jobs', $thrown);
dataset('released jobs', $released);
dataset('released jobs with delay', $releasedWithDelay);

dataset('jobs implementing nothing', $passed);
dataset('jobs implementing RunInLambda', $optsIn);
dataset('jobs implementing DoNotRunInLambda', $optsOut);
dataset('jobs implementing RunInLambda and DoNotRunInLambda', $optsInAndOut);
