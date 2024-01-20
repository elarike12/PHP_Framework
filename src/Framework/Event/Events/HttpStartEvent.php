<?php

/**
 * @copyright  Elar Must
 */

namespace Framework\Event\Events;

use Framework\Framework;

class HttpStartEvent {
    public function __construct(private Framework $framework) {
    }

    public function getFramework(): Framework {
        return $this->framework;
    }
}
