<?php
/**
 * This file is part of the PocketIO package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

use PocketIO\Client;
use PocketIO\Engine\SocketIO\SocketIO;

require __DIR__ . '/../../../vendor/autoload.php';

$client = new Client(new SocketIO('https://socket.cityzore.com'));

$client->initialize();
$client->emit('mesuttest', ['message' => 'TESTT']);
$client->close();
