<?php declare(strict_types=1);
/**
 * This file is part of the PocketIO package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace PocketIO\Engine\SocketIO;

use InvalidArgumentException;

/**
 * Represents the data for a Session
 */
class Session
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var float
     */
    private $heartbeat;

    /**
     * @var float[]
     */
    private $timeouts;

    /**
     * @var array
     */
    private $upgrades;

    public function __construct(string $id, float $interval, float $timeout, array $upgrades)
    {
        $this->id = $id;
        $this->upgrades = $upgrades;
        $this->heartbeat = microtime(true);

        $this->timeouts = [
            'timeout' => $timeout,
            'interval' => $interval
        ];
    }

    /**
     * The property should not be modified, hence the private accessibility on them
     *
     * @param string $prop
     * @return mixed
     */
    public function __get(string $prop)
    {
        static $list = ['id', 'upgrades'];

        if (!\in_array($prop, $list)) {
            throw new InvalidArgumentException(\sprintf('Unknown property "%s" for the Session object. Only the following are availables : ["%s"]', $prop, \implode('", "', $list)));
        }

        return $this->$prop;
    }

    /**
     * Checks whether a new heartbeat is necessary, and does a new heartbeat if it is the case
     *
     * @return Boolean true if there was a heartbeat, false otherwise
     */
    public function needsHeartbeat(): bool
    {
        if (0 < $this->timeouts['interval'] && microtime(true) > ($this->timeouts['interval'] + $this->heartbeat - 5)) {
            $this->heartbeat = microtime(true);
            return true;
        }
        return false;
    }
}
