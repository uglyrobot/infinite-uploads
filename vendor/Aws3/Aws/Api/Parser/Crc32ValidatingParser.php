<?php

namespace UglyRobot\Infinite_Uploads\Aws\Api\Parser;

use UglyRobot\Infinite_Uploads\Aws\Api\StructureShape;
use UglyRobot\Infinite_Uploads\Aws\CommandInterface;
use UglyRobot\Infinite_Uploads\Aws\Exception\AwsException;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface;
use UglyRobot\Infinite_Uploads\GuzzleHttp\Psr7;
/**
 * @internal Decorates a parser and validates the x-amz-crc32 header.
 */
class Crc32ValidatingParser extends \UglyRobot\Infinite_Uploads\Aws\Api\Parser\AbstractParser
{
    /**
     * @param callable $parser Parser to wrap.
     */
    public function __construct(callable $parser)
    {
        $this->parser = $parser;
    }
    public function __invoke(\UglyRobot\Infinite_Uploads\Aws\CommandInterface $command, \UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface $response)
    {
        if ($expected = $response->getHeaderLine('x-amz-crc32')) {
            $hash = hexdec(\UglyRobot\Infinite_Uploads\GuzzleHttp\Psr7\hash($response->getBody(), 'crc32b'));
            if ($expected != $hash) {
                throw new \UglyRobot\Infinite_Uploads\Aws\Exception\AwsException("crc32 mismatch. Expected {$expected}, found {$hash}.", $command, ['code' => 'ClientChecksumMismatch', 'connection_error' => true, 'response' => $response]);
            }
        }
        $fn = $this->parser;
        return $fn($command, $response);
    }
    public function parseMemberFromStream(\UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface $stream, \UglyRobot\Infinite_Uploads\Aws\Api\StructureShape $member, $response)
    {
        return $this->parser->parseMemberFromStream($stream, $member, $response);
    }
}
