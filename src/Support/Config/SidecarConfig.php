<?php

namespace Hammerstone\Sidecar\PHP\Support\Config;

use Hammerstone\Sidecar\PHP\Support\Config\Concerns\QueueConfigGetters;
use Hammerstone\Sidecar\PHP\Support\Config\Concerns\QueueConfigSetters;

class SidecarConfig
{
    use QueueConfigGetters;
    use QueueConfigSetters;

    public static function make()
    {
        return new static;
    }
}
