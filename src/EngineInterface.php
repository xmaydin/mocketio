<?php declare(strict_types=1);
/**
 * This file is part of the PocketIO package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace PocketIO;

/**
 * Represents an engine used within PocketIO to send / receive messages from
 * a websocket realtime server
 */
interface EngineInterface
{
    const OPEN = 0;
    const CLOSE = 1;
    const PING = 2;
    const PONG = 3;
    const MESSAGE = 4;
    const UPGRADE = 5;
    const NOOP = 6;

    /** Connect to the targeted server */
    public function connect();

    /** Closes the connection to the websocket */
    public function close();

    /**
     * Read data from the socket
     *
     * @return string Data read from the socket
     */
    public function read(): string;

    /**
     * Emits a message through the websocket
     *
     * @param string $event Event to emit
     * @param array $args Arguments to send
     */
    public function emit(string $event, array $args);

    /** Keeps alive the connection */
    public function keepAlive();

    /** Gets the name of the engine */
    public function getName(): string;

    /**
     * Sets the namespace for the next messages
     *
     * @param string $namespace the namespace
     */
    public function of(string $namespace);
}
