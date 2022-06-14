<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace Hammerstone\Sidecar\PHP;

use Aws\Result;
use Hammerstone\Sidecar\Clients\LambdaClient;
use Hammerstone\Sidecar\LambdaFunction;
use Hammerstone\Sidecar\Runtime;
use Laravel\SerializableClosure\SerializableClosure;

class PhpLambda extends LambdaFunction
{
    public static function mock(): void
    {
        if (app()->runningUnitTests() === false) {
            return;
        }

        test()->mock(LambdaClient::class)
            ->shouldReceive('invoke')
            ->andReturnUsing(fn (array $payload) => pipeline($payload)->through([
                carry(fn (array $payload) => json_decode($payload['Payload'], true)),
                carry(fn (array $payload) => $payload['closure']),
                carry(fn (string $encodedClosure) => base64_decode($encodedClosure)),
                carry(fn (string $serializedClosure) => unserialize($serializedClosure)),
                carry(fn (SerializableClosure $closure) => $closure()),
                carry(fn (array $data) => new Result([
                    'Payload' => json_encode($data),
                    'FunctionError' => '',
                    'LogResult' => base64_encode(''),
                ])),
            ])->thenReturn());
    }

    public function name()
    {
        return 'PHP-Lambda';
    }

    public function handler()
    {
        // Because we control the runtime, there is no
        // concept of a handler for this function.
        return 'noop';
    }

    public function timeout()
    {
        return 5;
    }

    public function runtime()
    {
        // Custom Alpine Linux runtime. The Vapor layer provides
        // the PHP, and we will provide the event loop.
        return Runtime::PROVIDED_AL2;
    }

    public function package()
    {
        return Package::make()->withVanillaPhp();
    }

    public function layers()
    {
        return [
            // Add the Vapor-provided PHP layer.
            VaporLayers::find(),
        ];
    }

    public function preparePayload($payload)
    {
        if ($this->shouldResetClosureBinding()) {
            $payload = $payload->bindTo(null, null);
        }

        return [
            'closure' => base64_encode(serialize(new SerializableClosure($payload))),
        ];
    }

    public function shouldResetClosureBinding()
    {
        // Make the closure fully anonymous by resetting the scope.
        return true;
    }

    public function variables()
    {
        return [
            // These two variables are required for the Vapor runtime to work properly.
            'PATH' => '/opt/bin:/usr/local/bin:/usr/bin/:/bin:/usr/local/sbin',
            'LD_LIBRARY_PATH' => '/opt/lib:/opt/lib/bref:/lib64:/usr/lib64:/var/runtime:/var/runtime/lib:/var/task:/var/task/lib',
        ];
    }
}
