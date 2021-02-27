<?php

namespace UglyRobot\Infinite_Uploads\GuzzleHttp\Psr7;

use UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface;
/**
 * Stream decorator that prevents a stream from being seeked
 */
class NoSeekStream implements \UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface
{
    use StreamDecoratorTrait;
    public function seek($offset, $whence = SEEK_SET)
    {
        throw new \RuntimeException('Cannot seek a NoSeekStream');
    }
    public function isSeekable()
    {
        return false;
    }
}
