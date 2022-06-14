<?php

namespace Hammerstone\Sidecar\PHP\Tests\Unit;

use Closure;
use Hammerstone\Sidecar\PHP\Events\LambdaJobProcessed;
use Hammerstone\Sidecar\PHP\Events\LambdaJobProcessing;
use Hammerstone\Sidecar\PHP\LaravelLambda;
use Hammerstone\Sidecar\PHP\Queue\LaravelLambdaWorker;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\FailedJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\PassedJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\ReleasedJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\ThrownJob;
use Hammerstone\Sidecar\PHP\Tests\Support\QueueTestHelper;
use Hammerstone\Sidecar\PHP\Tests\Support\SidecarTestHelper;
use Hammerstone\Sidecar\Results\SettledResult;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Bus\PendingChain;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    SidecarTestHelper::record()->enableQueueFeature(optin: true, queues: '*');
    app('events')->listen(JobProcessing::class, function (JobProcessing $event) {
        if ($event->job instanceof DatabaseJob) {
            $attempts = $event->job->payload()['attempts'] ?? $event->job->attempts();
            while ($event->job->attempts() < $attempts) {
                $event->job->getJobRecord()->increment();
            }
        }
    });
});

it('binds the extended queue worker when the sidecar queue feature is on', function () {
    SidecarTestHelper::record()->enableQueueFeature(optin: true, queues: '*');
    expect(app()->make('queue.worker'))->toBeInstanceOf(LaravelLambdaWorker::class);
});

it('does not bind the extended queue worker when the sidecar queue feature is off', function () {
    SidecarTestHelper::record()->disableQueueFeature();
    expect(app()->make('queue.worker'))->not->toBeInstanceOf(LaravelLambdaWorker::class);
});

it('does not run on lambda when the sidecar queue feature is off', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->disableQueueFeature();
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertNotQueued();
    $pendingJob->assertDeleted();
    $pendingJob->assertNotReleased();
    $pendingJob->assertNotExecutedOnLambda();
})->with('passed jobs');

it('can be opted-in and opted-out of running on lambda', function (QueueTestHelper $pendingJob, bool $mustOptIn, bool $optedInForLambdaExecution, bool $optedOutForLambdaExecution, bool $expected) {
    SidecarTestHelper::record()->enableQueueFeature(optin: $mustOptIn, queues: '*');
    $pendingJob->onQueue('lambda')->with([
        'optedInForLambdaExecution' => $optedInForLambdaExecution,
        'optedOutForLambdaExecution' => $optedOutForLambdaExecution,
    ])->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertNotQueued();
    $pendingJob->assertDeleted();
    $pendingJob->assertNotReleased();
    $pendingJob->assertExecutedOnLambda($expected ? 1 : 0);
})->with('passed jobs')->with([
    'when: [x] required; [x] optin; [x] optout; → then: [ ] on lambda' => [true, true, true, false],
    'when: [x] required; [ ] optin; [x] optout; → then: [ ] on lambda' => [true, false, true, false],
    'when: [x] required; [x] optin; [ ] optout; → then: [x] on lambda' => [true, true, false, true],
    'when: [x] required; [ ] optin; [ ] optout; → then: [ ] on lambda' => [true, false, false, false],
    'when: [ ] required; [x] optin; [x] optout; → then: [ ] on lambda' => [false, true, true, false],
    'when: [ ] required; [ ] optin; [x] optout; → then: [ ] on lambda' => [false, false, true, false],
    'when: [ ] required; [x] optin; [ ] optout; → then: [x] on lambda' => [false, true, false, true],
    'when: [ ] required; [ ] optin; [ ] optout; → then: [x] on lambda' => [false, false, false, true],
]);

it('can pass', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertNotQueued();
    $pendingJob->assertNotReleased();
    $pendingJob->assertNotDelayed();
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['failed', 'released', 'delay']))->toBe([
            'failed' => false,
            'released' => false,
            'delay' => 0,
        ]);
    });
})->with('passed jobs');

it('can release jobs', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertQueued();
    $pendingJob->assertReleased();
    $pendingJob->assertNotDelayed();
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['released', 'delay']))->toBe([
            'released' => true,
            'delay' => 0,
        ]);
    });
})->with('released jobs');

it('does not release the job within the lambda', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()
        ->enableQueueFeature(optin: false, queues: '*')
        ->transform(LaravelLambda::class, function (array $body) {
            // Why are we using releasing examples? Because the worker should be managing the queue, not the lambda, therefore the worker will receive these returned values.
            expect($body['released'])->toBe(true);
            return [...$body, 'released' => false];
        });
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertNotQueued();
    $pendingJob->assertDeleted();
    $pendingJob->assertNotReleased();
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['released', 'delay']))->toBe([
            'released' => false,
            'delay' => 0,
        ]);
    });
})->with('released jobs');

it('can release jobs with delay', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()
        ->enableQueueFeature(optin: false, queues: '*')
        ->transform(LaravelLambda::class, function (array $body) {
            // Why are we changing the delay? Because the worker should be managing the queue, not the lambda, therefore the worker will receive these returned values.
            expect($body['delay'])->toBe(10);
            return [...$body, 'delay' => 27];
        });
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertQueued();
    $pendingJob->assertReleased();
    $pendingJob->assertDelayed(27);
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['released', 'delay']))->toBe([
            'released' => true,
            'delay' => 27,
        ]);
    });
})->with('released jobs with delay');

it('can fail without retry [fail]', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->with([
        'attempts' => 1,
        'maxTries' => 3,
    ])->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertFailed();
    $pendingJob->assertNotQueued();
    $pendingJob->assertNotReleased();
    $pendingJob->assertNotDelayed();
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['failed', 'released', 'delay']))->toBe([
            'failed' => true, // The lambda flagged it as failed.
            'released' => false,
            'delay' => 0,
        ]);

        $exception = unserialize($result->body()['exception']);
        expect($exception)->toBeNull();
    });
})->with('failed jobs');

it('can fail and release for retry [exception]', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->with([
        'attempts' => 1,
        'maxTries' => 3,
    ])->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertQueued(); // Due to retry.
    $pendingJob->assertReleased(); // Due to retry.
    $pendingJob->assertNotDelayed();
    $pendingJob->assertTries(2);
    $pendingJob->assertMaxTries(3);
    $pendingJob->assertTriesRemaining(1);
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['failed', 'released', 'delay']))->toBe([
            'failed' => false, // Didn't fail because the lambda flagged it to.
            'released' => false, // Didn't release because the lambda flagged it to.
            'delay' => 0,
        ]);

        $exception = unserialize($result->body()['exception']);
        expect($exception)->not->toBeNull();
        expect($exception->getMessage())->toBeIn([
            "You're a terrible stuntman.",
            'A cooked goose for everyone!',
            "I'm just kidding. I could hear you. It was just really mean.",
            "They've done it! They've raised $50,000 for Frank's conveniently priced surgery!",
        ]);
    });
})->with('thrown jobs');

it('can fail when the max attempts is hit [fail]', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->with([
        'attempts' => 3,
        'maxTries' => 3,
    ])->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertFailed();
    $pendingJob->assertNotQueued();
    $pendingJob->assertDeleted();
    $pendingJob->assertNotReleased();
    $pendingJob->assertNotDelayed();
    $pendingJob->assertTries(3);
    $pendingJob->assertMaxTries(3);
    $pendingJob->assertTriesRemaining(0);
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['failed', 'released', 'delay']))->toBe([
            'failed' => true,
            'released' => false,
            'delay' => 0,
        ]);
    });
})->with('failed jobs');

it('can fail when the max attempts is hit [exception]', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->with([
        'attempts' => 3,
        'maxTries' => 3,
    ])->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertFailed();
    $pendingJob->assertNotQueued();
    $pendingJob->assertDeleted();
    $pendingJob->assertNotReleased();
    $pendingJob->assertNotDelayed();
    $pendingJob->assertTries(3);
    $pendingJob->assertMaxTries(3);
    $pendingJob->assertTriesRemaining(0);
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['failed', 'released', 'delay']))->toBe([
            'failed' => false,
            'released' => false,
            'delay' => 0,
        ]);
    });
})->with('thrown jobs');

it('handles dispatching jobs within the lambda', function () {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob = new QueueTestHelper(function () {
        dispatch(new PassedJob)->onQueue('lambda');
        dispatch(new FailedJob)->onQueue('lambda');
        dispatch(new ReleasedJob)->onQueue('lambda');
        dispatch(new ThrownJob)->onQueue('lambda');
    }, fn (Closure $closure) => dispatch($closure));
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued(1);
    $pendingJob->assertExecutedOnLambda(0);

    // We want to assert that lambda is not dispatching or logging failed jobs.
    $queuedCount = $pendingJob->countQueuedJobs();
    $failedCount = $pendingJob->countFailedJobs();
    app('events')->listen(JobProcessing::class, function () use ($pendingJob, &$queuedCount, &$failedCount) {
        // Before the lambda starts processing, get the counts for queued and failed.
        $queuedCount = $pendingJob->countQueuedJobs();
        $failedCount = $pendingJob->countFailedJobs();
    });
    app('events')->listen(LambdaJobProcessed::class, function () use ($pendingJob, &$queuedCount, &$failedCount) {
        // When the lambda is processed, we expect that the lambda should not have dispatched or failed any jobs.
        expect($pendingJob->countQueuedJobs())->toBe($queuedCount);
        expect($pendingJob->countFailedJobs())->toBe($failedCount);
    });

    // Run Closure
    $pendingJob->runQueueWorker();
    expect($queuedCount)->toBe(1); // Dispatch does not happen within the lambda.
    expect($failedCount)->toBe(0); // Fail does not happen within the lambda.
    $pendingJob->assertQueued(4); // Dispatch happens after the lambda executes within the worker.
    $pendingJob->assertNotFailed(); // Fail happens after the lambda executes within the worker.
    $pendingJob->assertReleased(0);
    $pendingJob->assertExecutedOnLambda(1);

    // Run PassedJob
    $pendingJob->runQueueWorker();
    expect($queuedCount)->toBe(4); // Dispatch does not happen within the lambda.
    expect($failedCount)->toBe(0); // Fail does not happen within the lambda.
    $pendingJob->assertQueued(3); // Dispatch happens after the lambda executes within the worker.
    $pendingJob->assertFailed(0); // Fail happens after the lambda executes within the worker.
    $pendingJob->assertReleased(0);
    $pendingJob->assertExecutedOnLambda(2);

    // Run FailedJob
    $pendingJob->runQueueWorker();
    expect($queuedCount)->toBe(3); // Dispatch does not happen within the lambda.
    expect($failedCount)->toBe(0); // Fail does not happen within the lambda.
    $pendingJob->assertQueued(2); // Dispatch happens after the lambda executes within the worker.
    $pendingJob->assertFailed(1); // Fail happens after the lambda executes within the worker.
    $pendingJob->assertReleased(0);
    $pendingJob->assertExecutedOnLambda(3);

    // Run ReleasedJob
    $pendingJob->runQueueWorker();
    expect($queuedCount)->toBe(2); // Dispatch does not happen within the lambda.
    expect($failedCount)->toBe(1); // Fail does not happen within the lambda.
    $pendingJob->assertQueued(2); // Dispatch happens after the lambda executes within the worker.
    $pendingJob->assertFailed(1); // Fail happens after the lambda executes within the worker.
    $pendingJob->assertReleased(1);
    $pendingJob->assertExecutedOnLambda(4);

    // Run ThrownJob
    $pendingJob->runQueueWorker();
    expect($queuedCount)->toBe(2); // Dispatch does not happen within the lambda.
    expect($failedCount)->toBe(1); // Fail does not happen within the lambda.
    $pendingJob->assertQueued(1); // Dispatch happens after the lambda executes within the worker.
    $pendingJob->assertFailed(2); // Fail happens after the lambda executes within the worker.
    $pendingJob->assertReleased(1);
    $pendingJob->assertExecutedOnLambda(5);
});

it('can work job chains', function () {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    Storage::persistentFake()->put('test.txt', '');
    $pendingJob = new QueueTestHelper(Bus::chain([
        fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'a' . PHP_EOL),
        fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'b' . PHP_EOL),
        fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'c' . PHP_EOL),
        fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'd' . PHP_EOL),
    ]), fn (PendingChain $chain) => $chain->dispatch());
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued(1);
    $pendingJob->assertExecutedOnLambda(0);
    expect(Storage::persistentFake()->get('test.txt'))->toBe('');

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(1);
    $pendingJob->assertExecutedOnLambda(1);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', '']));

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(1);
    $pendingJob->assertExecutedOnLambda(2);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', 'b', '']));

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(1);
    $pendingJob->assertExecutedOnLambda(3);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', 'b', 'c', '']));

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(0);
    $pendingJob->assertExecutedOnLambda(4);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', 'b', 'c', 'd', '']));
});

it('can work failing job chains and triggers the catch callback', function () {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    Storage::persistentFake()->put('test.txt', '');
    app('events')->listen(LambdaJobProcessing::class, fn () => config(['sidecar.is_executing_in_lambda' => true]));
    app('events')->listen(LambdaJobProcessed::class, fn () => config(['sidecar.is_executing_in_lambda' => false]));
    $pendingJob = new QueueTestHelper(
        Bus::chain([
            fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'a' . PHP_EOL),
            fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'b' . PHP_EOL),
            new FailedJob,
            fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'c' . PHP_EOL),
            fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'd' . PHP_EOL),
        ])->catch(
            fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . (config('sidecar.is_executing_in_lambda', false) ? 'CATCH HANDLED IN LAMBDA' : 'CATCH HANDLED IN WORKER') . PHP_EOL)
        ),
        fn (PendingChain $chain) => $chain->dispatch(),
    );
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued(1);
    $pendingJob->assertExecutedOnLambda(0);
    expect(Storage::persistentFake()->get('test.txt'))->toBe('');

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(1);
    $pendingJob->assertExecutedOnLambda(1);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', '']));

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(1);
    $pendingJob->assertExecutedOnLambda(2);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', 'b', '']));

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(0);
    $pendingJob->assertFailed(1);
    $pendingJob->assertExecutedOnLambda(3);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, [
        'a',
        'b',
        'CATCH HANDLED IN LAMBDA',
        '',
    ]));
});

it('can work a job batch', function () {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    Storage::persistentFake()->put('test.txt', '');
    $pendingJob = new QueueTestHelper(
        Bus::batch([])
            ->add([
                fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'a' . PHP_EOL),
                fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'b' . PHP_EOL),
                fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'c' . PHP_EOL),
                fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'd' . PHP_EOL),
            ]),
        fn (PendingBatch $batch) => $batch->dispatch(),
    );
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued(4);
    $pendingJob->assertExecutedOnLambda(0);
    $batchId = DB::table('job_batches')->value('id');
    expect(Storage::persistentFake()->get('test.txt'))->toBe('');
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 4,
        'processedJobs' => 0,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(3);
    $pendingJob->assertExecutedOnLambda(1);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 3,
        'processedJobs' => 1,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(2);
    $pendingJob->assertExecutedOnLambda(2);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', 'b', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 2,
        'processedJobs' => 2,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(1);
    $pendingJob->assertExecutedOnLambda(3);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', 'b', 'c', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 1,
        'processedJobs' => 3,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(0);
    $pendingJob->assertExecutedOnLambda(4);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', 'b', 'c', 'd', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 0,
        'processedJobs' => 4,
        'failedJobs' => 0,
    ]);
});

it('can work a job batch then trigger then and finally callbacks', function () {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    Storage::persistentFake()->put('test.txt', '');
    app('events')->listen(LambdaJobProcessing::class, fn () => config(['sidecar.is_executing_in_lambda' => true]));
    app('events')->listen(LambdaJobProcessed::class, fn () => config(['sidecar.is_executing_in_lambda' => false]));
    $pendingJob = new QueueTestHelper(
        Bus::batch([])
            ->add([
                fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'a' . PHP_EOL),
                fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'b' . PHP_EOL),
                fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'c' . PHP_EOL),
                fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'd' . PHP_EOL),
            ])
            ->catch(fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'NO FAILURE EXPECTED' . PHP_EOL))
            ->then(fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . (config('sidecar.is_executing_in_lambda', false) ? 'FIRST THEN HANDLED IN LAMBDA' : 'FIRST THEN HANDLED IN WORKER') . PHP_EOL))
            ->finally(fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . (config('sidecar.is_executing_in_lambda', false) ? 'FIRST FINALLY HANDLED IN LAMBDA' : 'FIRST FINALLY HANDLED IN WORKER') . PHP_EOL))
            ->then(fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . (config('sidecar.is_executing_in_lambda', false) ? 'SECOND THEN HANDLED IN LAMBDA' : 'SECOND THEN HANDLED IN WORKER') . PHP_EOL))
            ->finally(fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . (config('sidecar.is_executing_in_lambda', false) ? 'SECOND FINALLY HANDLED IN LAMBDA' : 'SECOND FINALLY HANDLED IN WORKER') . PHP_EOL)),
        fn (PendingBatch $batch) => $batch->dispatch(),
    );
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued(4);
    $pendingJob->assertExecutedOnLambda(0);
    $batchId = DB::table('job_batches')->value('id');
    expect(Storage::persistentFake()->get('test.txt'))->toBe('');
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 4,
        'processedJobs' => 0,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(3);
    $pendingJob->assertExecutedOnLambda(1);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 3,
        'processedJobs' => 1,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(2);
    $pendingJob->assertExecutedOnLambda(2);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', 'b', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 2,
        'processedJobs' => 2,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(1);
    $pendingJob->assertExecutedOnLambda(3);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', 'b', 'c', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 1,
        'processedJobs' => 3,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(0);
    $pendingJob->assertExecutedOnLambda(4);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, [
        'a',
        'b',
        'c',
        'd',
        'FIRST THEN HANDLED IN LAMBDA',
        'SECOND THEN HANDLED IN LAMBDA',
        'FIRST FINALLY HANDLED IN LAMBDA',
        'SECOND FINALLY HANDLED IN LAMBDA',
        '',
    ]));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 0,
        'processedJobs' => 4,
        'failedJobs' => 0,
    ]);
});

it('can work a job batch with failures allowed', function () {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    Storage::persistentFake()->put('test.txt', '');
    app('events')->listen(LambdaJobProcessing::class, fn () => config(['sidecar.is_executing_in_lambda' => true]));
    app('events')->listen(LambdaJobProcessed::class, fn () => config(['sidecar.is_executing_in_lambda' => false]));
    $pendingJob = new QueueTestHelper(
        Bus::batch([])
            ->add([
                // Note: failure is allowed, so the batch should not cancel.
                fn () => Bus::findBatch(DB::table('job_batches')->value('id'))->cancelled() ? null : Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'a' . PHP_EOL),
                new FailedJob,
                fn () => Bus::findBatch(DB::table('job_batches')->value('id'))->cancelled() ? null : Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'c' . PHP_EOL),
                new FailedJob,
                fn () => Bus::findBatch(DB::table('job_batches')->value('id'))->cancelled() ? null : Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'e' . PHP_EOL),
            ])
            ->then(fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . (config('sidecar.is_executing_in_lambda', false) ? 'THEN HANDLED IN LAMBDA' : 'THEN HANDLED IN WORKER') . PHP_EOL))
            ->catch(fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . (config('sidecar.is_executing_in_lambda', false) ? 'CATCH HANDLED IN LAMBDA' : 'CATCH HANDLED IN WORKER') . PHP_EOL))
            ->finally(fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . (config('sidecar.is_executing_in_lambda', false) ? 'FINALLY HANDLED IN LAMBDA' : 'FINALLY HANDLED IN WORKER') . PHP_EOL))
            ->allowFailures(),
        fn (PendingBatch $batch) => $batch->dispatch(),
    );
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued(5);
    $pendingJob->assertExecutedOnLambda(0);
    $batchId = DB::table('job_batches')->value('id');
    expect(Bus::findBatch($batchId)->cancelled())->toBe(false);
    expect(Storage::persistentFake()->get('test.txt'))->toBe('');
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 5,
        'pendingJobs' => 5,
        'processedJobs' => 0,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(4);
    $pendingJob->assertExecutedOnLambda(1);
    expect(Bus::findBatch($batchId)->cancelled())->toBe(false);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 5,
        'pendingJobs' => 4,
        'processedJobs' => 1,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(3);
    $pendingJob->assertExecutedOnLambda(2);
    expect(Bus::findBatch($batchId)->cancelled())->toBe(false);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, [
        'a',
        'CATCH HANDLED IN LAMBDA',
        '',
    ]));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 5,
        'pendingJobs' => 4,
        'processedJobs' => 1,
        'failedJobs' => 1,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(2);
    $pendingJob->assertExecutedOnLambda(3);
    expect(Bus::findBatch($batchId)->cancelled())->toBe(false);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, [
        'a',
        'CATCH HANDLED IN LAMBDA',
        'c',
        '',
    ]));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 5,
        'pendingJobs' => 3,
        'processedJobs' => 2,
        'failedJobs' => 1,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(1);
    $pendingJob->assertExecutedOnLambda(4);
    expect(Bus::findBatch($batchId)->cancelled())->toBe(false);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, [
        'a',
        'CATCH HANDLED IN LAMBDA',
        'c',
        // CATCH only triggers once
        '',
    ]));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 5,
        'pendingJobs' => 3,
        'processedJobs' => 2,
        'failedJobs' => 2,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(0);
    $pendingJob->assertExecutedOnLambda(5);
    expect(Bus::findBatch($batchId)->cancelled())->toBe(false);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, [
        'a',
        'CATCH HANDLED IN LAMBDA',
        'c',
        'e',
        // THEN should not be called because a failure happened
        'FINALLY HANDLED IN LAMBDA',
        '',
    ]));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 5,
        'pendingJobs' => 2,
        'processedJobs' => 3,
        'failedJobs' => 2,
    ]);
});

it('can work a job batch then trigger catch and finally callbacks', function () {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    Storage::persistentFake()->put('test.txt', '');
    app('events')->listen(LambdaJobProcessing::class, fn () => config(['sidecar.is_executing_in_lambda' => true]));
    app('events')->listen(LambdaJobProcessed::class, fn () => config(['sidecar.is_executing_in_lambda' => false]));
    $pendingJob = new QueueTestHelper(
        Bus::batch([])
            ->add([
                // Note: failure is not allowed, so the batch should cancel.
                fn () => Bus::findBatch(DB::table('job_batches')->value('id'))->cancelled() ? null : Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'a' . PHP_EOL),
                new FailedJob,
                fn () => Bus::findBatch(DB::table('job_batches')->value('id'))->cancelled() ? null : Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'c' . PHP_EOL),
                fn () => Bus::findBatch(DB::table('job_batches')->value('id'))->cancelled() ? null : Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'd' . PHP_EOL),
            ])
            ->then(fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . (config('sidecar.is_executing_in_lambda', false) ? 'THEN HANDLED IN LAMBDA' : 'THEN HANDLED IN WORKER') . PHP_EOL))
            ->catch(fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . (config('sidecar.is_executing_in_lambda', false) ? 'CATCH HANDLED IN LAMBDA' : 'CATCH HANDLED IN WORKER') . PHP_EOL))
            ->finally(fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . (config('sidecar.is_executing_in_lambda', false) ? 'FINALLY HANDLED IN LAMBDA' : 'FINALLY HANDLED IN WORKER') . PHP_EOL)),
        fn (PendingBatch $batch) => $batch->dispatch(),
    );
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued(4);
    $pendingJob->assertExecutedOnLambda(0);
    $batchId = DB::table('job_batches')->value('id');
    expect(Bus::findBatch($batchId)->cancelled())->toBe(false);
    expect(Storage::persistentFake()->get('test.txt'))->toBe('');
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 4,
        'processedJobs' => 0,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(3);
    $pendingJob->assertExecutedOnLambda(1);
    expect(Bus::findBatch($batchId)->cancelled())->toBe(false);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 3,
        'processedJobs' => 1,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(2);
    $pendingJob->assertExecutedOnLambda(2);
    expect(Bus::findBatch($batchId)->cancelled())->toBe(true);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, [
        'a',
        'CATCH HANDLED IN LAMBDA',
        '',
    ]));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 3,
        'processedJobs' => 1,
        'failedJobs' => 1,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(1);
    $pendingJob->assertExecutedOnLambda(3);
    expect(Bus::findBatch($batchId)->cancelled())->toBe(true);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, [
        'a',
        'CATCH HANDLED IN LAMBDA',
        // 'c' was not added because of the "batch cancelled" guard check
        '',
    ]));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 2,
        'processedJobs' => 2,
        'failedJobs' => 1,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(0);
    $pendingJob->assertExecutedOnLambda(4);
    expect(Bus::findBatch($batchId)->cancelled())->toBe(true);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, [
        'a',
        'CATCH HANDLED IN LAMBDA',
        // 'c' was not added because of the "batch cancelled" guard check
        // 'd' was not added because of the "batch cancelled" guard check
        // THEN is not called because a failure happened
        'FINALLY HANDLED IN LAMBDA',
        '',
    ]));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 1,
        'processedJobs' => 3,
        'failedJobs' => 1,
    ]);
});

it('can work a job batch with chains in it', function () {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    Storage::persistentFake()->put('test.txt', '');
    app('events')->listen(LambdaJobProcessing::class, fn () => config(['sidecar.is_executing_in_lambda' => true]));
    app('events')->listen(LambdaJobProcessed::class, fn () => config(['sidecar.is_executing_in_lambda' => false]));
    $pendingJob = new QueueTestHelper(
        Bus::batch([])
            ->add([
                fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'a' . PHP_EOL),
                [
                    fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'b' . PHP_EOL),
                    fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'c' . PHP_EOL),
                ],
                fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'd' . PHP_EOL),
            ]),
        fn (PendingBatch $batch) => $batch->dispatch(),
    );
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued(3); // a, b, d
    $pendingJob->assertExecutedOnLambda(0);
    $batchId = DB::table('job_batches')->value('id');
    expect(Storage::persistentFake()->get('test.txt'))->toBe('');
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 4,
        'processedJobs' => 0,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker(); // a, b, d
    $pendingJob->assertQueued(2); // b, d
    $pendingJob->assertExecutedOnLambda(1);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 3,
        'processedJobs' => 1,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker(); // b, d
    $pendingJob->assertQueued(2); // d, c
    $pendingJob->assertExecutedOnLambda(2);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', 'b', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 2,
        'processedJobs' => 2,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker(); // d, c
    $pendingJob->assertQueued(1); // c
    $pendingJob->assertExecutedOnLambda(3);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', 'b', 'd', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 1,
        'processedJobs' => 3,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker(); // c
    $pendingJob->assertQueued(0); // empty
    $pendingJob->assertExecutedOnLambda(4);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', 'b', 'd', 'c', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 4,
        'pendingJobs' => 0,
        'processedJobs' => 4,
        'failedJobs' => 0,
    ]);
});

it('can work a job batch where jobs are added dynamically after dispatch', function () {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    Storage::persistentFake()->put('test.txt', '');
    $pendingJob = new QueueTestHelper(
        Bus::batch([])
            ->add([
                fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'a' . PHP_EOL),
                fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'b' . PHP_EOL),
                fn () => Bus::findBatch(DB::table('job_batches')->value('id'))->add([
                    fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'c' . PHP_EOL),
                    fn () => Storage::persistentFake()->put('test.txt', Storage::persistentFake()->get('test.txt') . 'd' . PHP_EOL),
                ]),
            ]),
        fn (PendingBatch $batch) => $batch->dispatch(),
    );
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued(3);
    $pendingJob->assertExecutedOnLambda(0);
    $batchId = DB::table('job_batches')->value('id');
    expect(Storage::persistentFake()->get('test.txt'))->toBe('');
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 3,
        'pendingJobs' => 3,
        'processedJobs' => 0,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(2);
    $pendingJob->assertExecutedOnLambda(1);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 3,
        'pendingJobs' => 2,
        'processedJobs' => 1,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(1);
    $pendingJob->assertExecutedOnLambda(2);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', 'b', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 3,
        'pendingJobs' => 1,
        'processedJobs' => 2,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(2);
    $pendingJob->assertExecutedOnLambda(3);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', 'b', ''])); // Jobs were added to the batch on this run
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 5,
        'pendingJobs' => 2,
        'processedJobs' => 3,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(1);
    $pendingJob->assertExecutedOnLambda(4);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', 'b', 'c', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 5,
        'pendingJobs' => 1,
        'processedJobs' => 4,
        'failedJobs' => 0,
    ]);

    $pendingJob->runQueueWorker();
    $pendingJob->assertQueued(0);
    $pendingJob->assertExecutedOnLambda(5);
    expect(Storage::persistentFake()->get('test.txt'))->toBe(implode(PHP_EOL, ['a', 'b', 'c', 'd', '']));
    expect(Arr::only(Bus::findBatch($batchId)->toArray(), ['totalJobs', 'pendingJobs', 'processedJobs', 'failedJobs']))->toBe([
        'totalJobs' => 5,
        'pendingJobs' => 0,
        'processedJobs' => 5,
        'failedJobs' => 0,
    ]);
});

it('logs but not within the lambda', function () {
    Storage::persistentFake()->put('log.txt', '');
    config([
        'logging.default' => 'single',
        'logging.channels.single.path' => Storage::persistentFake()->path('log.txt'),
    ]);
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob = new QueueTestHelper(function () {
        dispatch(fn () => Log::info('puppy'))->onQueue('lambda');
        dispatch(fn () => Log::debug('kitten'))->onQueue('lambda');
        dispatch(fn () => Log::error('fish'))->onQueue('lambda');
    }, fn (Closure $closure) => dispatch($closure));
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->runQueueWorker();

    expect(Str::of(Storage::persistentFake()->get('log.txt'))->contains('.INFO: puppy'))->toBe($hasPuppy = false);
    expect(Str::of(Storage::persistentFake()->get('log.txt'))->contains('.DEBUG: kitten'))->toBe($hasKitten = false);
    expect(Str::of(Storage::persistentFake()->get('log.txt'))->contains('.ERROR: fish'))->toBe($hasFish = false);

    app('events')->listen(LambdaJobProcessed::class, function () use (&$hasPuppy, &$hasKitten, &$hasFish) {
        expect(Str::of(Storage::persistentFake()->get('log.txt'))->contains('.INFO: puppy'))->toBe($hasPuppy);
        expect(Str::of(Storage::persistentFake()->get('log.txt'))->contains('.DEBUG: kitten'))->toBe($hasKitten);
        expect(Str::of(Storage::persistentFake()->get('log.txt'))->contains('.ERROR: fish'))->toBe($hasFish);
    });

    $pendingJob->runQueueWorker();
    // LambdaJobProcessed event listener asserts that the lambda did not add anything to the logs. Currently: false, false, false
    expect(Str::of(Storage::persistentFake()->get('log.txt'))->contains('.INFO: puppy'))->toBe($hasPuppy = true); // Logged outside of the lambda.
    expect(Str::of(Storage::persistentFake()->get('log.txt'))->contains('.DEBUG: kitten'))->toBe($hasKitten = false);
    expect(Str::of(Storage::persistentFake()->get('log.txt'))->contains('.ERROR: fish'))->toBe($hasFish = false);

    $pendingJob->runQueueWorker();
    // LambdaJobProcessed event listener asserts that the lambda did not add anything to the logs. Currently: true, false, false
    expect(Str::of(Storage::persistentFake()->get('log.txt'))->contains('.INFO: puppy'))->toBe($hasPuppy = true);
    expect(Str::of(Storage::persistentFake()->get('log.txt'))->contains('.DEBUG: kitten'))->toBe($hasKitten = true); // Logged outside of the lambda.
    expect(Str::of(Storage::persistentFake()->get('log.txt'))->contains('.ERROR: fish'))->toBe($hasFish = false);

    $pendingJob->runQueueWorker();
    // LambdaJobProcessed event listener asserts that the lambda did not add anything to the logs. Currently: true, true, false
    expect(Str::of(Storage::persistentFake()->get('log.txt'))->contains('.INFO: puppy'))->toBe($hasPuppy = true);
    expect(Str::of(Storage::persistentFake()->get('log.txt'))->contains('.DEBUG: kitten'))->toBe($hasKitten = true);
    expect(Str::of(Storage::persistentFake()->get('log.txt'))->contains('.ERROR: fish'))->toBe($hasFish = true); // Logged outside of the lambda.
});
