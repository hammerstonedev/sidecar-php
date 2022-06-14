<?php
/**
 * @author Aaron Francis <aaron@hammerstone.dev|https://twitter.com/aarondfrancis>
 */

namespace Hammerstone\Sidecar\PHP\Tests;

use Hammerstone\Sidecar\Deployment;
use Hammerstone\Sidecar\PHP\LaravelLambda;
use Hammerstone\Sidecar\PHP\PhpLambda;
use Hammerstone\Sidecar\PHP\Providers\SidecarPhpServiceProvider;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Providers\EventServiceProvider;
use Hammerstone\Sidecar\PHP\Tests\Support\QueueTestHelper;
use Hammerstone\Sidecar\PHP\Tests\Support\SidecarTestHelper;
use Hammerstone\Sidecar\Providers\SidecarServiceProvider;
use Hammerstone\Sidecar\Sidecar;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    static $deployed = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('sidecar.testing.mock_php_lambda')) {
            PhpLambda::mock();
        }

        $this->beKindAndRewind(function () {
            QueueTestHelper::reset();
            SidecarTestHelper::reset();
        });
    }

    protected function defineEnvironment($app)
    {
        $this->loadEnvironmentVariables($app);

        config()->set('view.paths', [
            $this->packagePath('tests/Support/resources/views'),
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(
            $this->packagePath('tests/Support/database/migrations')
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            EventServiceProvider::class,
            SidecarPhpServiceProvider::class,
        ];
    }

    protected function beKindAndRewind($callback): void
    {
        $this->beforeApplicationDestroyed($callback);
    }

    public function loadEnvironmentVariables($app): self
    {
        $directory = $this->packagePath();
        $app->useEnvironmentPath($directory);
        $app->make(LoadEnvironmentVariables::class)->bootstrap($app);
        $app->make(LoadConfiguration::class)->bootstrap($app);

        config(['sidecar.functions' => [LaravelLambda::class]]);
        config(['sidecar.testing.mock_php_lambda' => $mocking = (bool) env('MOCK_PHP_LAMBDA', true)]);

        (new SidecarServiceProvider($app))->register();
        if (static::$deployed === false && $mocking === false) {
            static::$deployed = true;
            Sidecar::addLogger(function ($message) {
                echo $message . PHP_EOL;
            });
            Deployment::make()->deploy()->activate(true);
        }

        return $this;
    }

    protected function packagePath($path = ''): string
    {
        $path = '/' . ltrim($path, '/');

        return rtrim(realpath(__DIR__ . '/..') . $path, '/');
    }
}
