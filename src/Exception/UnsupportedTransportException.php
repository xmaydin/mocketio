<?php declare(strict_types=1);
/**
 * This file is part of the PocketIO package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace PocketIO\Exception;

use Exception;
use RuntimeException;

class UnsupportedTransportException extends RuntimeException
{
    public function __construct($transport, Exception $previous = null)
    {
        parent::__construct(
            sprintf('This server does not support the %s transport, aborting', $transport),
            0,
            $previous
        );
    }
}
