<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support;

use Closure;
use Hammerstone\Sidecar\Events\AfterFunctionExecuted;
use Hammerstone\Sidecar\PHP\Support\Config\SidecarConfig;

class SidecarTestHelper extends SidecarConfig
{
    private static bool $recording = false;
    private static array $executions = [];
    private static array $transformers = [];
    private static array $settledHooks = [];

    public function __construct()
    {
        if (static::$recording) {
            return;
        }

        static::$recording = true;

        app('events')->listen(AfterFunctionExecuted::class, function (AfterFunctionExecuted $event) {
            $body = $event->result->body();
            $lambda = $event->function::class;
            $result = $event->result->rawAwsResult();

            foreach (static::$transformers[$lambda] ?? [] as $transformer) {
                $result['Payload'] = json_encode($transformer($body));
            }

            foreach (static::$settledHooks[$lambda] ?? [] as $onSettled) {
                $onSettled($body);
            }

            static::$executions[$lambda][] = $event;
        });
    }

    public static function record(): self
    {
        return new static;
    }

    public function fresh(): self
    {
        static::reset();

        return $this;
    }

    public static function reset(): void
    {
        static::$recording = false;
        static::$executions = [];
        static::$transformers = [];
        static::$settledHooks = [];
    }

    public function transform(string $lambda, Closure $callback): self
    {
        static::$transformers[$lambda][] = $callback;

        return $this;
    }

    public function onSettled(string $lambda, Closure $callback): self
    {
        static::$settledHooks[$lambda] = $callback;

        return $this;
    }

    public function assertWasExecuted(int $times, string $lambda, ?Closure $filter = null): self
    {
        $executions = collect(static::$executions[$lambda] ?? [])->map->result->when(
            $filter !== null,
            fn ($executions) => $executions->filter(fn ($value, $key) => $filter($value, $key) ?? true),
        );

        expect($executions->count())->toBe($times);

        return $this;
    }
}
