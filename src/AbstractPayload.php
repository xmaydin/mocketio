<?php declare(strict_types=1);
/**
 * This file is part of the PocketIO package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace PocketIO;

/**
 * Payload for sending data through the websocket
 */
abstract class AbstractPayload
{
    const OPCODE_NON_CONTROL_RESERVED_1 = 0x3;
    const OPCODE_NON_CONTROL_RESERVED_2 = 0x4;
    const OPCODE_NON_CONTROL_RESERVED_3 = 0x5;
    const OPCODE_NON_CONTROL_RESERVED_4 = 0x6;
    const OPCODE_NON_CONTROL_RESERVED_5 = 0x7;

    const OPCODE_CONTINUE = 0x0;
    const OPCODE_TEXT = 0x1;
    const OPCODE_BINARY = 0x2;
    const OPCODE_CLOSE = 0x8;
    const OPCODE_PING = 0x9;
    const OPCODE_PONG = 0xA;

    const OPCODE_CONTROL_RESERVED_1 = 0xB;
    const OPCODE_CONTROL_RESERVED_2 = 0xC;
    const OPCODE_CONTROL_RESERVED_3 = 0xD;
    const OPCODE_CONTROL_RESERVED_4 = 0xE;
    const OPCODE_CONTROL_RESERVED_5 = 0xF;

    /**
     * @var int
     */
    protected $fin = 0b1; // only one frame is necessary

    /**
     * @var int[]
     */
    protected $rsv = [0b0, 0b0, 0b0]; // rsv1, rsv2, rsv3

    /**
     * @var bool
     */
    protected $mask = false;

    /**
     * @var string
     */
    protected $maskKey = "\x00\x00\x00\x00";

    /**
     * @var int
     */
    protected  $opCode;

    /**
     * Mask a data according to the current mask key
     *
     * @param string $data Data to mask
     * @return string Masked data
     */
    protected function maskData(string $data): string
    {
        $masked = '';
        $data = str_split($data);
        $key = str_split($this->maskKey);

        foreach ($data as $i => $letter) {
            $masked .= $letter ^ $key[$i % 4];
        }

        return $masked;
    }
}
