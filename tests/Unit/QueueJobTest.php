<?php

namespace Hammerstone\Sidecar\PHP\Tests\Unit;

use Hammerstone\Sidecar\PHP\Tests\Support\QueueTestHelper;
use Hammerstone\Sidecar\PHP\Tests\Support\SidecarTestHelper;
use Illuminate\Support\Str;

/** @see \Hammerstone\Sidecar\PHP\Tests\Unit\WorkJobTest for how we make use of the payload's `optedInForLambdaExecution` and `optedOutForLambdaExecution` values. */

beforeEach(function () {
    SidecarTestHelper::record()->enableQueueFeature(optin: true, queues: '*');
});

test('given the job implements RunInLambda, then optedInForLambdaExecution is set to true in the payload', function (QueueTestHelper $pendingJob) {
    $payload = $pendingJob->onQueue('lambda')->payload();

    expect($payload['optedInForLambdaExecution'])->toBe(true);
    expect($payload['optedOutForLambdaExecution'])->toBe(false);
})->with('jobs implementing RunInLambda');

test('given the job does not implement RunInLambda, then optedInForLambdaExecution is set to false in the payload', function (QueueTestHelper $pendingJob) {
    $payload = $pendingJob->onQueue('lambda')->payload();

    expect($payload['optedInForLambdaExecution'])->toBe(false);
    expect($payload['optedOutForLambdaExecution'])->toBe(false);
})->with('jobs implementing nothing');

test('given the job implements DoNotRunInLambda, then optedOutForLambdaExecution is set to true in the payload', function (QueueTestHelper $pendingJob, string $onQueue, string|array $allowedQueues) {
    SidecarTestHelper::record()->enableQueueFeature(optin: true, queues: $allowedQueues);
    $payload = $pendingJob->onQueue($onQueue)->payload();

    expect($payload['optedInForLambdaExecution'])->toBe(false);
    expect($payload['optedOutForLambdaExecution'])->toBe(true);
})->with('jobs implementing DoNotRunInLambda')->with([
    'all queues are allowed (string config value)' => ['lambda', '*'],
    'the queue is allowed (string config value)' => ['lambda', 'lambda'],
    'the queue is not allowed (string config value)' => ['not-lambda', 'lambda'],
    'all queues are allowed (array config value)' => ['lambda', ['*']],
    'the queue is allowed (array config value)' => ['lambda', ['lambda']],
    'the queue is not allowed (array config value)' => ['not-lambda', ['lambda']],
    'allowed queues contains * with other values (array config value)' => ['not-lambda', ['lambda', '*']],
]);

test('given the job implements both RunInLambda and DoNotRunInLambda, then optedOutForLambdaExecution is set to true in the payload', function (QueueTestHelper $pendingJob, string $onQueue, string|array $allowedQueues) {
    SidecarTestHelper::record()->enableQueueFeature(optin: true, queues: $allowedQueues);
    $payload = $pendingJob->onQueue($onQueue)->payload();

    expect($payload['optedInForLambdaExecution'])->toBe(true);
    expect($payload['optedOutForLambdaExecution'])->toBe(true);
})->with('jobs implementing RunInLambda and DoNotRunInLambda')->with([
    'all queues are allowed (string config value)' => ['lambda', '*'],
    'the queue is allowed (string config value)' => ['lambda', 'lambda'],
    'the queue is not allowed (string config value)' => ['not-lambda', 'lambda'],
    'all queues are allowed (array config value)' => ['lambda', ['*']],
    'the queue is allowed (array config value)' => ['lambda', ['lambda']],
    'the queue is not allowed (array config value)' => ['not-lambda', ['lambda']],
    'allowed queues contains * with other values (array config value)' => ['not-lambda', ['lambda', '*']],
]);

test('given the job does not implement DoNotRunInLambda, then optedOutForLambdaExecution is true in the payload only when the queue is not allowed', function (QueueTestHelper $pendingJob, string $onQueue, string|array $allowedQueues, bool $expected) {
    SidecarTestHelper::record()->enableQueueFeature(optin: true, queues: $allowedQueues);
    $payload = $pendingJob->onQueue($onQueue)->payload();
    if ($expected && Str::endsWith($pendingJob->job::class, 'Listener') && $onQueue !== 'lambda') {
        test()->markTestSkipped('Cannot specify which queue the listeners should use from an event.');
    }

    expect($payload['optedInForLambdaExecution'])->toBe(false);
    expect($payload['optedOutForLambdaExecution'])->toBe($expected);
})->with('jobs implementing nothing')->with([
    'all queues are allowed (string config value)' => ['lambda', '*', false],
    'the queue is allowed (string config value)' => ['lambda', 'lambda', false],
    'the queue is not allowed (string config value)' => ['not-lambda', 'lambda', true],
    'all queues are allowed (array config value)' => ['lambda', ['*'], false],
    'the queue is allowed (array config value)' => ['lambda', ['lambda'], false],
    'the queue is not allowed (array config value)' => ['not-lambda', ['lambda'], true],
    'allowed queues contains * with other values (array config value)' => ['not-lambda', ['lambda', '*'], false],
]);
