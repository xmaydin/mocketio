<?php declare(strict_types=1);
/**
 * This file is part of the PocketIO package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace PocketIO\Exception;

use BadMethodCallException;
use Exception;
use PocketIO\EngineInterface;

class UnsupportedActionException extends BadMethodCallException
{
    public function __construct(EngineInterface $engine, $action, Exception $previous = null)
    {
        parent::__construct(
            sprintf('The action "%s" is not supported by the engine "%s"', $engine->getName(), $action),
            0,
            $previous
        );
    }
}
