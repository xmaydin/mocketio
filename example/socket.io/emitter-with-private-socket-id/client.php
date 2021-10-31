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

$token = 'this_is_peter_token';

$client = new Client(new SocketIO('http://localhost:1337', [
    'headers' => [
        'X-My-Header: websocket rocks',
        'Authorization: Bearer ' . $token,
        'User: peter',
    ]
]));

$data = [
    'message' => 'How are you?',
    'token' => $token,
];

$client->initialize();
$client->emit('private_chat_message', $data);
$client->close();

$token = 'this_is_peter_token';

$client = new Client(new SocketIO('http://localhost:1337', [
    'headers' => [
        'X-My-Header: websocket rocks',
        'Authorization: Bearer ' . $token,
        'User: peter',
    ]
]));

$data = [
    'message' => 'Do you remember me?',
    'token' => $token,
];

$client->initialize();
$client->emit('private_chat_message', $data);

$invalidToken = 'this_is_invalid_peter_token';

$data = [
    'message' => 'Do you remember me?',
    'token' => $invalidToken,
];
$client->emit('private_chat_message', $data);
$client->close();
