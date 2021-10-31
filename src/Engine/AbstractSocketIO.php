<?php declare(strict_types=1);
/**
 * This file is part of the PocketIO package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace PocketIO\Engine;

use DomainException;
use PocketIO\Engine\SocketIO\Session;
use RuntimeException;
use PocketIO\EngineInterface;
use PocketIO\Payload\Decoder;
use PocketIO\Exception\UnsupportedActionException;
use PocketIO\Exception\MalformedUrlException;

abstract class AbstractSocketIO implements EngineInterface
{
    const CONNECT = 0;
    const DISCONNECT = 1;
    const EVENT = 2;
    const ACK = 3;
    const ERROR = 4;
    const BINARY_EVENT = 5;
    const BINARY_ACK = 6;

    /** @var string[] Parse url result */
    protected array $url;

    /** @var array cookies received during handshake */
    protected array $cookies = [];

    /** @var Session Session information */
    protected Session $session;

    /** @var array Array of options for the engine */
    protected array $options;

    /** @var resource Resource to the connected stream */
    protected $stream;

    /** @var string the namespace of the next message */
    protected string $namespace = '';

    /** @var array Array of php stream context options */
    protected $context = [];

    public function __construct($url, array $options = [])
    {
        $this->url = $this->parseUrl($url);

        if (isset($options['context'])) {
            $this->context = $options['context'];
            unset($options['context']);
        }

        $this->options = array_replace($this->getDefaultOptions(), $options);
    }

    /** {@inheritDoc} */
    public function connect()
    {
        throw new UnsupportedActionException($this, 'connect');
    }

    /** {@inheritDoc} */
    public function keepAlive()
    {
    }

    /** {@inheritDoc} */
    public function close()
    {
        throw new UnsupportedActionException($this, 'close');
    }

    /** {@inheritDoc} */
    public function of(string $namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * Write the message to the socket
     *
     * @param integer $code type of message (one of EngineInterface constants)
     * @param string|null $message Message to send, correctly formatted
     */
    abstract public function write(int $code, string $message = null);

    /** {@inheritDoc} */
    public function emit(string $event, array $args)
    {
        throw new UnsupportedActionException($this, 'emit');
    }

    /**
     * Network safe file read wrapper
     *
     * @param integer $bytes
     * @return string
     */
    protected function readBytes(int $bytes): string
    {
        $data = '';
        $chunk = null;
        while ($bytes > 0 && false !== ($chunk = fread($this->stream, $bytes))) {
            $bytes -= strlen($chunk);
            $data .= $chunk;
        }

        if (false === $chunk) {
            throw new RuntimeException('Could not read from stream');
        }
        return $data;
    }

    /**
     * {@inheritDoc}
     *
     * Be careful, this method may hang your script, as we're not in a non
     * blocking mode.
     */
    public function read(): string
    {
        $this->keepAlive();

        $data = $this->readBytes(2);
        $bytes = unpack('C*', $data);

        $mask = ($bytes[2] & 0b10000000) >> 7;
        $length = $bytes[2] & 0b01111111;

        switch ($length) {
            case 0x7D: // 125
                break;

            case 0x7E: // 126
                $data .= $bytes = $this->readBytes(2);
                $bytes = \unpack('n', $bytes);

                if (empty($bytes[1])) {
                    throw new RuntimeException('Invalid extended packet len');
                }

                $length = $bytes[1];
                break;

            case 0x7F: // 127
                if (8 > PHP_INT_SIZE) {
                    throw new DomainException('64 bits unsigned integer are not supported on this architecture');
                }

                $data .= $bytes = $this->readBytes(8);
                list($left, $right) = array_values(unpack('N2', $bytes));
                $length = $left << 32 | $right;
                break;
        }

        if ($mask) $data .= $this->readBytes(4);

        $data .= $this->readBytes($length);

        return (string)new Decoder($data);
    }

    /** {@inheritDoc} */
    public function getName(): string
    {
        return 'SocketIO';
    }

    /**
     * Parse an url into parts we may expect
     *
     * @param string $url
     *
     * @return string[] information on the given URL
     */
    protected function parseUrl(string $url): array
    {
        $parsed = parse_url($url);

        if (false === $parsed) {
            throw new MalformedUrlException($url);
        }

        $server = array_replace(['scheme' => 'http',
            'host' => 'localhost',
            'query' => []
        ], $parsed);

        if (!isset($server['port'])) {
            $server['port'] = 'https' === $server['scheme'] ? 443 : 80;
        }

        if (!isset($server['path']) || $server['path'] == '/') {
            $server['path'] = 'socket.io';
        }

        if (!is_array($server['query'])) {
            parse_str($server['query'], $query);
            $server['query'] = $query;
        }

        $server['secured'] = 'https' === $server['scheme'];

        return $server;
    }

    /**
     * Get the defaults options
     *
     * @return array mixed[] Defaults options for this engine
     */
    protected function getDefaultOptions(): array
    {
        return [
            'debug' => false,
            'wait' => 0,
            'timeout' => ini_get("default_socket_timeout")
        ];
    }
}
