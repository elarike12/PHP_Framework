<?php

/**
 * @copyright  Elar Must
 */

namespace Framework\Event\Events;

use OpenSwoole\WebSocket\Server;
use Psr\EventDispatcher\StoppableEventInterface;

class WebSocketCloseEvent implements StoppableEventInterface {
    private bool $stopped = false;

    public function __construct(private Server $server, private int $connectionId) {
    }

    public function getServer(): Server {
        return $this->server;
    }

    public function getConnectionId(): int {
        return $this->connectionId;
    }

    public function stopEvent(): void {
        $this->stopped = true;
    }

    public function isPropagationStopped(): bool {
        return $this->stopped;
    }
}
