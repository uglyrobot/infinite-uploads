<?php

namespace UglyRobot\Infinite_Uploads\Aws\Handler\GuzzleV5;

use UglyRobot\Infinite_Uploads\GuzzleHttp\Stream\StreamDecoratorTrait;
use UglyRobot\Infinite_Uploads\GuzzleHttp\Stream\StreamInterface as GuzzleStreamInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface as Psr7StreamInterface;
/**
 * Adapts a PSR-7 Stream to a Guzzle 5 Stream.
 *
 * @codeCoverageIgnore
 */
class GuzzleStream implements \UglyRobot\Infinite_Uploads\GuzzleHttp\Stream\StreamInterface
{
    use StreamDecoratorTrait;
    /** @var Psr7StreamInterface */
    private $stream;
    public function __construct(\UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface $stream)
    {
        $this->stream = $stream;
    }
}
