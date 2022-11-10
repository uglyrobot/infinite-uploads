<?php

namespace UglyRobot\Infinite_Uploads\Aws\Handler\GuzzleV5;

use UglyRobot\Infinite_Uploads\GuzzleHttp\Stream\StreamDecoratorTrait;
use UglyRobot\Infinite_Uploads\GuzzleHttp\Stream\StreamInterface as GuzzleStreamInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface as Psr7StreamInterface;
/**
 * Adapts a Guzzle 5 Stream to a PSR-7 Stream.
 *
 * @codeCoverageIgnore
 */
class PsrStream implements \UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface
{
    use StreamDecoratorTrait;
    /** @var GuzzleStreamInterface */
    private $stream;
    public function __construct(\UglyRobot\Infinite_Uploads\GuzzleHttp\Stream\StreamInterface $stream)
    {
        $this->stream = $stream;
    }
    public function rewind()
    {
        $this->stream->seek(0);
    }
    public function getContents()
    {
        return $this->stream->getContents();
    }
}
