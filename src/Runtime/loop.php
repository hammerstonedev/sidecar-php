<?php
/**
 * Adapted from the Vapor Core CLI Runtime, used under the MIT License.
 * Originally authored by Taylor Otwell, Nuno Maduro, and Mohamed Said.
 *
 * @see https://github.com/laravel/vapor-core/blob/2.0/stubs/cliRuntime.php
 */

use Hammerstone\Sidecar\PHP\Support\CustomLambdaRuntime;
use Hammerstone\Sidecar\PHP\Support\ProcessRunner;
use Illuminate\Contracts\Console\Kernel;
use Laravel\Vapor\Runtime\LambdaContainer;
use Laravel\Vapor\Runtime\StorageDirectories;

echo 'Starting Sidecar\'s event handling loop' . PHP_EOL;

// Differentiate between a full Laravel app and a simple PHP runtime.
if (($_ENV['SIDECAR_IS_FULL_LARAVEL'] ?? false) === 'true') {
    with(require __DIR__ . '/bootstrap/app.php', function ($app) {
        StorageDirectories::create();

        $app->useStoragePath(StorageDirectories::PATH);

        echo 'Caching Laravel configuration' . PHP_EOL;

        try {
            $app->make(Kernel::class)->call('config:cache');
        } catch (Throwable $e) {
            echo 'Failed to cache Laravel configuration: ' . $e->getMessage() . PHP_EOL;
        }
    });
}

$invocations = 0;

$lambdaRuntime = CustomLambdaRuntime::fromEnvironmentVariable();

while (true) {
    $lambdaRuntime->nextInvocation(function ($invocationId, $event) use ($lambdaRuntime) {
        return ProcessRunner::make($lambdaRuntime)->handle($invocationId, $event);
    });

    LambdaContainer::terminateIfInvocationLimitHasBeenReached(
        ++$invocations, (int)($_ENV['SIDECAR_MAX_INVOCATIONS'] ?? 250)
    );
}

