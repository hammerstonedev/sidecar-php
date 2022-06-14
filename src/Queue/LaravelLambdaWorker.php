<?php

namespace Hammerstone\Sidecar\PHP\Queue;

use Closure;
use Hammerstone\Sidecar\PHP\Events\LambdaJobProcessed;
use Hammerstone\Sidecar\PHP\Events\LambdaJobProcessing;
use Hammerstone\Sidecar\PHP\Exceptions\UnsupportedQueueDriverException;
use Hammerstone\Sidecar\PHP\LaravelLambda;
use Hammerstone\Sidecar\PHP\Support\Decorator;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Jobs\BeanstalkdJob;
use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Queue\Jobs\JobName;
use Illuminate\Queue\Jobs\RedisJob;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use ReflectionClass;
use ReflectionFunction;
use Throwable;

class LaravelLambdaWorker extends Worker
{
    public function process($connectionName, $job, WorkerOptions $options)
    {
        $lambdaJob = new class ($job) extends Decorator {
            public function fire()
            {
                $this->invade(function () {
                    $payload = $this->payload();

                    $enabled = config('sidecar.queue.enabled', false);
                    $optInRequired = config('sidecar.queue.opt_in_required', true);
                    $optedIn = Arr::get($payload, 'optedInForLambdaExecution', false);
                    $optedOut = Arr::get($payload, 'optedOutForLambdaExecution', false);

                    if (! $enabled) {
                        return $this->fire();
                    }

                    if ($optedOut) {
                        return $this->fire();
                    }

                    if ($optInRequired && ! $optedIn) {
                        return $this->fire();
                    }

                    [$class, $method] = JobName::parse($payload['job']);
                    $data = $payload['data'];
                    $queue = $this->queue;
                    $connectionName = $this->connectionName;
                    $this->instance = $this->resolve($class);

                    $payload['id'] ??= $this->getJobId();
                    $payload['attempts'] ??= $this->attempts();
                    $payload['maxTries'] ??= $this->maxTries();
                    $payload['maxExceptions'] ??= $this->maxExceptions();
                    $payload['backoff'] ??= $this->backoff();
                    $payload['timeout'] ??= $this->timeout();
                    $payload['retryUntil'] ??= $this->retryUntil();
                    $payload['failOnTimeout'] ??= $this->shouldFailOnTimeout();

                    // BEGIN LAMBDA CODE
                    $result = LaravelLambda::execute(function () use ($class, $method, $data, $queue, $payload, $connectionName) {
                        // We will not log within the lambda. We will collect logs and return them in the payload.
                        $logs = collect();
                        Log::swap(Log::build(['driver' => 'null']));
                        Log::listen(fn (MessageLogged $event) => $logs->push($event));

                        // Turn off the queue - the worker will dispatch/delete/fail/release jobs based on the returned payload.
                        config([
                            'queue.default' => 'null',
                            'queue.failed.driver' => 'null',
                            'queue.connections.null.driver' => 'null',
                        ]);
                        app()->forgetInstance('queue');
                        app()->forgetInstance('queue.failer');
                        app()->forgetInstance('queue.connection');
                        app()->forgetInstance(BatchRepository::class);
                        app()->forgetInstance(DatabaseBatchRepository::class);
                        Queue::fake();

                        // Switch to SyncJob because it catches everything that we want to be return to the worker.
                        $exception = null;
                        $container = app();
                        $rawPayload = json_encode($payload);
                        $job = new class($container, $rawPayload, $connectionName, $queue) extends SyncJob {
                            public $delay = 0;

                            public function release($delay = 0)
                            {
                                parent::release($this->delay = $delay);
                            }

                            public function attempts()
                            {
                                return $this->payload()['attempts'] ?? 1;
                            }

                            public function getJobId()
                            {
                                return $this->payload()['id'] ?? null;
                            }
                        };

                        event(new LambdaJobProcessing($connectionName, $job));

                        try {
                            $container->make($class)->{$method}($job, $data);
                        } catch (Throwable $error) {
                            $cursor = $error;

                            $reflection = new ReflectionClass($error::class);
                            while ($reflection->hasProperty('trace') === false) {
                                $reflection = $reflection->getParentClass();
                            }
                            $prop = tap($reflection->getProperty('trace'))->setAccessible(true);

                            do {
                                $trace = $prop->getValue($cursor);
                                foreach ($trace as &$call) {
                                    array_walk_recursive($call['args'], function (&$value, $key) {
                                        if ($value instanceof Closure) {
                                            $reflection = new ReflectionFunction($value);
                                            $value = sprintf('Closure(%s:%s)', $reflection->getFileName(), $reflection->getStartLine());
                                        } elseif (is_object($value)) {
                                            $value = sprintf('Object(%s)', get_class($value));
                                        } elseif (is_resource($value)) {
                                            $value = sprintf('Resource(%s)', get_resource_type($value));
                                        }
                                    });
                                }
                                $prop->setValue($cursor, $trace);
                            } while ($cursor = $cursor->getPrevious());
                            $prop->setAccessible(false);
                            $exception = $error;
                        }

                        return tap([
                            'failed' => $job->hasFailed(),
                            'exception' => serialize($exception),
                            'released' => $job->isReleased(),
                            'delay' => (int) $job->delay,
                            'jobs' => collect(Queue::pushedJobs())->collapse()->map(fn ($item) => serialize($item['job']))->all(),
                            'logs' => $logs->map(fn (MessageLogged $event) => [$event->level, $event->message, $event->context])->all(),
                        ], fn () => event(new LambdaJobProcessed($connectionName, $job)));
                    })->throw()->body();
                    // END LAMBDA CODE

                    // Add to log.
                    foreach ($result['logs'] as $log) {
                        Log::log(...$log);
                    }

                    // Unserialize the exception.
                    $exception = unserialize($result['exception']);

                    // Remove batch id and chain callbacks from the payload because they are handled within the lambda.
                    $payloadData = $this->payload()['data'];
                    $payloadData['command'] = unserialize($payloadData['command']);
                    if (data_get($payloadData['command'], 'batchId') || data_get($payloadData['command'], 'chainCatchCallbacks')) {
                        data_set($payloadData['command'], 'batchId', null);
                        data_set($payloadData['command'], 'chainCatchCallbacks', null);
                        $payloadData['command'] = serialize($payloadData['command']);

                        $scrubbedPayload = json_encode([...$this->payload(), 'data' => $payloadData]);

                        match ($this::class) {
                            BeanstalkdJob::class => Closure::bind(fn () => $this->data = $scrubbedPayload, $this->job, BeanstalkdJob::class)(),
                            DatabaseJob::class => $this->job->payload = $scrubbedPayload,
                            RedisJob::class => $this->job = $scrubbedPayload,
                            SqsJob::class => $this->job['Body'] = $scrubbedPayload,
                            SyncJob::class => $this->job = $scrubbedPayload,
                            default => throw new UnsupportedQueueDriverException(
                                'We do not yet support "job batching" or "job chain catch callbacks" for your queue driver. A PR contribution would be appreciated.'
                            ),
                        };
                    }

                    // Dispatch any returned jobs.
                    foreach (array_map('unserialize', $result['jobs']) as $job) {
                        $batch = method_exists($job, 'batch') ? $job->batch() : null;
                        $queue = data_get($batch, 'options.queue', $job?->queue);
                        $connection = data_get($batch, 'options.connection', $job?->connection);
                        app('queue')->connection($connection)->pushOn($queue, $job);
                    }

                    // Pushes to failed_jobs and deletes the current job.
                    if ($result['failed']) {
                        return $this->fail($exception);
                    }

                    // Worker will release for retry, or mark as failed for reaching either max attempts or max exceptions.
                    if ($exception) {
                        throw $exception;
                    }

                    // Dispatches the job for another attempt and deletes the current job.
                    if ($result['released']) {
                        return $this->release($result['delay']);
                    }

                    // Deletes the current job.
                    $this->delete();
                });
            }
        };

        parent::process($connectionName, $lambdaJob, $options);
    }

    protected function raiseBeforeJobEvent($connectionName, $job)
    {
        parent::raiseBeforeJobEvent($connectionName, $job->getDecorated());
    }

    protected function markJobAsFailedIfAlreadyExceedsMaxAttempts($connectionName, $job, $maxTries)
    {
        parent::markJobAsFailedIfAlreadyExceedsMaxAttempts($connectionName, $job->getDecorated(), $maxTries);
    }

    protected function raiseAfterJobEvent($connectionName, $job)
    {
        parent::raiseAfterJobEvent($connectionName, $job->getDecorated());
    }

    protected function handleJobException($connectionName, $job, WorkerOptions $options, Throwable $e)
    {
        parent::handleJobException($connectionName, $job->getDecorated(), $options, $e);
    }
}
