<?php declare(strict_types=1);
/**
 * This file is part of the PocketIO package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace PocketIO\Payload;

use PocketIO\AbstractPayload;

/**
 * Encode the payload before sending it to a frame
 */
class Encoder extends AbstractPayload
{
    /**
     * @var string
     */
    private $data;
    /**
     * @var string
     */
    private $payload;

    /**
     * @param string $data data to encode
     * @param integer $opCode OpCode to use (one of AbstractPayload's constant)
     * @param bool $mask Should we use a mask ?
     */
    public function __construct(string $data, int $opCode, bool $mask)
    {
        $this->data = $data;
        $this->opCode = $opCode;
        $this->mask = $mask;

        if (true === $this->mask) {
            $this->maskKey = openssl_random_pseudo_bytes(4);
        }
    }

    public function encode()
    {
        if (isset($this->payload) && !is_null($this->payload)) {
            return;
        }

        $pack = '';
        $length = strlen($this->data);

        if (0xFFFF < $length) {
            $pack = pack('NN', ($length & 0xFFFFFFFF00000000) >> 0b100000, $length & 0x00000000FFFFFFFF);
            $length = 0x007F;
        } elseif (0x007D < $length) {
            $pack = pack('n*', $length);
            $length = 0x007E;
        }

        $payload = ($this->fin << 0b001) | $this->rsv[0];
        $payload = ($payload << 0b001) | $this->rsv[1];
        $payload = ($payload << 0b001) | $this->rsv[2];
        $payload = ($payload << 0b100) | $this->opCode;
        $payload = ($payload << 0b001) | $this->mask;
        $payload = ($payload << 0b111) | $length;

        $data = $this->data;
        $payload = pack('n', $payload) . $pack;

        if (true === $this->mask) {
            $payload .= $this->maskKey;
            $data = $this->maskData($data);
        }

        $this->payload = $payload . $data;
    }

    public function __toString()
    {
        $this->encode();

        return $this->payload;
    }
}
