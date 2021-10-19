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

class ServerConnectionFailureException extends RuntimeException
{
    /** @var string php error message */
    private string $errorMessage;

    public function __construct(string $errorMessage, Exception $previous = null)
    {
        parent::__construct('An error occurred while trying to establish a connection to the server', 0, $previous);

        $this->errorMessage = $errorMessage;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }
}
