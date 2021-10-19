<?php
/**
 * This file is part of the PocketIO package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace PocketIO\Engine\SocketIO;

use Exception;
use InvalidArgumentException;
use UnexpectedValueException;

use PocketIO\EngineInterface;
use PocketIO\Payload\Encoder;
use PocketIO\Engine\AbstractSocketIO;

use PocketIO\Exception\SocketException;
use PocketIO\Exception\UnsupportedTransportException;
use PocketIO\Exception\ServerConnectionFailureException;

/**
 * Socket.IO version v2-3-4.x
 */
class SocketIO extends AbstractSocketIO
{
    const TRANSPORT_POLLING = 'polling';
    const TRANSPORT_WEBSOCKET = 'websocket';

    /** {@inheritDoc} */
    public function connect()
    {
        if (is_resource($this->stream)) {
            return;
        }

        $this->handshake();

        $protocol = 'http';
        $errors = [null, null];
        $host = sprintf('%s:%d', $this->url['host'], $this->url['port']);

        if ($this->url['secured']) {
            $protocol = 'ssl';
            $host = 'ssl://' . $host;
        }

        // add custom headers
        if (isset($this->options['headers'])) {
            $headers = $this->context[$protocol]['header'] ?? [];
            $this->context[$protocol]['header'] = array_merge($headers, $this->options['headers']);
        }

        $this->stream = stream_socket_client(
            $host,
            $errors[0],
            $errors[1],
            $this->options['timeout'],
            STREAM_CLIENT_CONNECT,
            stream_context_create($this->context)
        );

        if (!is_resource($this->stream)) {
            throw new SocketException($errors[0], $errors[1]);
        }

        stream_set_timeout($this->stream, $this->options['timeout']);

        $this->upgradeTransport();
    }

    /** {@inheritDoc}
     * @throws Exception
     */
    public function close()
    {
        if (!is_resource($this->stream)) {
            return;
        }

        $this->write(EngineInterface::CLOSE);

        fclose($this->stream);
        unset($this->stream);
        unset($this->session);
        $this->cookies = [];
    }

    /** {@inheritDoc}
     * @throws Exception
     */
    public function emit(string $event, array $args)
    {
        $this->keepAlive();
        $namespace = $this->namespace;

        if ('' !== $namespace) {
            $namespace .= ',';
        }

        return $this->write(EngineInterface::MESSAGE, static::EVENT . $namespace . json_encode([$event, $args]));
    }

    /** {@inheritDoc}
     * @throws Exception
     */
    public function of(string $namespace)
    {
        $this->keepAlive();
        parent::of($namespace);

        $this->write(EngineInterface::MESSAGE, static::CONNECT . $namespace);
    }

    /** {@inheritDoc}
     * @throws Exception
     */
    public function write(int $code, string $message = null)
    {
        if (!is_resource($this->stream)) {
            return;
        }

        if (!is_int($code) || 0 > $code || 6 < $code) {
            throw new InvalidArgumentException('Wrong message type when trying to write on the socket');
        }

        $payload = new Encoder($code . $message, Encoder::OPCODE_TEXT, true);
        $bytes = @fwrite($this->stream, (string)$payload);

        if (!$bytes) {
            throw new Exception("Message was not delivered");
        }

        // wait a little bit of time after this message was sent
        usleep((int)$this->options['wait']);

        return $bytes;
    }

    /** {@inheritDoc} */
    public function getName(): string
    {
        $defaults = parent::getDefaultOptions();
        return 'SocketIO Version ' . $defaults['version'] . '.X';
    }

    /** {@inheritDoc} */
    protected function getDefaultOptions(): array
    {
        $defaults = parent::getDefaultOptions();

        $defaults['version'] = 3;
        $defaults['use_b64'] = false;
        $defaults['transport'] = static::TRANSPORT_POLLING;

        return $defaults;
    }

    /** Does the handshake with the Socket.io server and populates the `session` value object */
    protected function handshake()
    {
        if (isset($this->session) && !is_null($this->session)) {
            return;
        }

        $query = [
            'use_b64' => $this->options['use_b64'],
            'EIO' => $this->options['version'],
            'transport' => $this->options['transport']
        ];

        if (isset($this->url['query'])) {
            $query = array_replace($query, $this->url['query']);
        }

        $context = $this->context;
        $protocol = is_bool($this->url['secured']) && $this->url['secured'] ? 'ssl' : 'http';

        if (!isset($context[$protocol])) {
            $context[$protocol] = [];
        }

        // add customer headers
        if (isset($this->options['headers'])) {
            $headers = $context['http']['header'] ?? [];
            $context['http']['header'] = array_merge($headers, $this->options['headers']);
        }

        $url = sprintf(
            '%s://%s:%d/%s/?%s',
            $this->url['scheme'],
            $this->url['host'],
            $this->url['port'],
            \trim($this->url['path'], '/'),
            \http_build_query($query)
        );

        $result = @file_get_contents($url, false, stream_context_create($context));

        if (!$result) {
            $message = null;
            $error = error_get_last();

            if (!is_null($error) && !strpos($error['message'], 'file_get_contents()')) {
                $message = $error['message'];
            }

            throw new ServerConnectionFailureException($message);
        }

        $open_curly_at = \strpos($result, '{');
        $todeCode = substr($result, $open_curly_at, strrpos($result, '}') - $open_curly_at + 1);
        $decoded = json_decode($todeCode, true);

        if (!in_array('websocket', $decoded['upgrades'])) {
            throw new UnsupportedTransportException('websocket');
        }

        $cookies = [];
        foreach ($http_response_header as $header) {
            if (preg_match('/^Set-Cookie:\s*([^;]*)/i', $header, $matches)) {
                $cookies[] = $matches[1];
            }
        }
        $this->cookies = $cookies;

        $this->session = new Session(
            $decoded['sid'],
            $decoded['pingInterval'] / 1000,
            $decoded['pingTimeout'] / 1000,
            $decoded['upgrades']
        );
    }

    /**
     * Upgrades the transport to WebSocket
     *
     * FYI:
     * Version "2" is used for the EIO param by socket.io v1
     * Version "3" is used by socket.io v2
     * @throws Exception
     */
    protected function upgradeTransport()
    {
        $query = [
            'sid' => $this->session->id,
            'EIO' => $this->options['version'],
            'transport' => static::TRANSPORT_WEBSOCKET
        ];

        if ($this->options['version'] === 2) {
            $query['use_b64'] = $this->options['use_b64'];
        }

        $url = sprintf('/%s/?%s', \trim($this->url['path'], '/'), http_build_query($query));

        $hash = sha1(uniqid(mt_rand(), true), true);

        if ($this->options['version'] !== 2) {
            $hash = substr($hash, 0, 16);
        }

        $key = base64_encode($hash);

        $origin = '*';
        $headers = isset($this->context['headers']) ? (array)$this->context['headers'] : [];

        foreach ($headers as $header) {
            $matches = [];

            if (\preg_match('`^Origin:\s*(.+?)$`', $header, $matches)) {
                $origin = $matches[1];
                break;
            }
        }

        $request = "GET {$url} HTTP/1.1\r\n"
            . "Host: {$this->url['host']}:{$this->url['port']}\r\n"
            . "Upgrade: WebSocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: {$key}\r\n"
            . "Sec-WebSocket-Version: 13\r\n"
            . "Origin: {$origin}\r\n";

        if (!empty($this->cookies)) {
            $request .= "Cookie: " . implode('; ', $this->cookies) . "\r\n";
        }

        $request .= "\r\n";

        fwrite($this->stream, $request);
        $result = $this->readBytes(12);

        if ('HTTP/1.1 101' !== $result) {
            throw new UnexpectedValueException(
                \sprintf('The server returned an unexpected value. Expected "HTTP/1.1 101", had "%s"', $result)
            );
        }

        // cleaning up the stream
        while ('' !== trim(fgets($this->stream)));

        $this->write(EngineInterface::UPGRADE);

        //remove message '40' from buffer, emmiting by socket.io after receiving EngineInterface::UPGRADE
        if ($this->options['version'] === 2) {
            if (stream_get_meta_data($this->stream)["unread_bytes"] !== 0) {
                $this->read();
            }
        }
    }

    /**
     * {@inheritDoc}
     * @throws Exception
     */
    public function keepAlive()
    {
        if ($this->session->needsHeartbeat()) {
            $this->write(static::PING);
        }
    }
}
