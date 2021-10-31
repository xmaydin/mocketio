<?php
/**
 * This file is part of the PocketIO package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace PocketIO;

use ReflectionMethod;
use ReflectionProperty;
use PHPUnit\Framework\TestCase;

class AbstractPayloadTest extends TestCase
{
    public function testMaskData()
    {
        $payload = new Payload;

        $refl = new ReflectionProperty('PocketIO\\Payload', 'maskKey');
        $refl->setAccessible(true);
        $refl->setValue($payload, '?EV!');

        $refl = new ReflectionMethod('PocketIO\\Payload', 'maskData');
        $refl->setAccessible(true);

        $this->assertSame('592a39', bin2hex($refl->invoke($payload, 'foo')));
    }
}

/** Fixtures for these tests */
class Payload extends AbstractPayload { }
