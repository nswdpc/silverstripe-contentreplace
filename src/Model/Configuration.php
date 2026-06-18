<?php

namespace Symbiote\ContentReplace\Model;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

/**
 * Configuration model for module
 */
class Configuration
{
    use Injectable;
    use Configurable;

    private static array $applicable_controllers = [];
}
