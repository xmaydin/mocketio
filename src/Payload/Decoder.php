<?php declare(strict_types=1);
/**
 * This file is part of the PocketIO package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace PocketIO\Payload;

use Countable;
use PocketIO\AbstractPayload;

/**
 * Decode the payload from a received frame
 */
class Decoder extends AbstractPayload implements Countable
{
    private string $payload;
    private string $data = '';

    private int $length;

    /** @param string $payload Payload to decode */
    public function __construct(string $payload)
    {
        $this->payload = $payload;
    }

    public function decode()
    {
        $length = count($this);

        // if ($payload !== null) and ($payload packet error)?
        // invalid websocket packet data or not (text, binary opCode)
        if (3 > $length) {
            return;
        }

        $payload = array_map('ord', str_split($this->payload));

        $this->fin = ($payload[0] >> 0b111);

        $this->rsv = [
            ($payload[0] >> 0b110) & 0b1,
            ($payload[0] >> 0b101) & 0b1,
            ($payload[0] >> 0b100) & 0b1
        ];

        $this->opCode = $payload[0] & 0xF;
        $this->mask = (bool)($payload[1] >> 0b111);

        $payloadOffset = 2;

        if ($length > 125) {
            $payloadOffset = (0xFFFF < $length && 0xFFFFFFFF >= $length) ? 6 : 4;
        }

        $payload = implode('', array_map('chr', $payload));

        if ($this->mask) {
            $this->maskKey = substr($payload, $payloadOffset, 4);
            $payloadOffset += 4;
        }

        $data = substr($payload, $payloadOffset, $length);

        if ($this->mask) {
            $data = $this->maskData($data);
        }

        $this->data = $data;
    }

    public function count()
    {
        if (is_null($this->payload)) return 0;
        if (isset($this->length) && !is_null($this->length)) return $this->length;

        $length = ord($this->payload[1]) & 0x7F;

        if ($length == 126 || $length == 127) {
            $length = unpack('H*', substr($this->payload, 2, ($length == 126 ? 2 : 4)));
            $length = hexdec($length[1]);
        }

        return $this->length = $length;
    }

    public function __toString()
    {
        $this->decode();

        return $this->data ?: '';
    }
}
