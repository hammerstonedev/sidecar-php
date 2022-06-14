<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace Hammerstone\Sidecar\PHP;

class LaravelLambda extends PhpLambda
{
    public function name()
    {
        return 'Laravel-Lambda';
    }

    public function package()
    {
        return Package::make()
            ->withFullApplication()
            ->exclude($this->exclude());
    }

    public function exclude()
    {
        return [
            '.env',
            '.git',
            'node_modules',
            'storage',
            'bootstrap/cache/*',
            'public',
        ];
    }

    public function variables()
    {
        return array_merge(parent::variables(), [
            'APP_CONFIG_CACHE' => '/tmp/storage/bootstrap/cache/config.php',
            'APP_EVENTS_CACHE' => '/tmp/storage/bootstrap/cache/events.php',
            'APP_PACKAGES_CACHE' => '/tmp/storage/bootstrap/cache/packages.php',
            'APP_ROUTES_CACHE' => '/tmp/storage/bootstrap/cache/routes-v7.php',
            'APP_SERVICES_CACHE' => '/tmp/storage/bootstrap/cache/services.php',
            'SIDECAR_IS_FULL_LARAVEL' => 'true',
        ]);
    }
}
