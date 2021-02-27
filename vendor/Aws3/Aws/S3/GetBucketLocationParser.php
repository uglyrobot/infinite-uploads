<?php

namespace UglyRobot\Infinite_Uploads\Aws\S3;

use UglyRobot\Infinite_Uploads\Aws\Api\Parser\AbstractParser;
use UglyRobot\Infinite_Uploads\Aws\Api\StructureShape;
use UglyRobot\Infinite_Uploads\Aws\CommandInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface;
/**
 * @internal Decorates a parser for the S3 service to correctly handle the
 *           GetBucketLocation operation.
 */
class GetBucketLocationParser extends \UglyRobot\Infinite_Uploads\Aws\Api\Parser\AbstractParser
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
        $fn = $this->parser;
        $result = $fn($command, $response);
        if ($command->getName() === 'GetBucketLocation') {
            $location = 'us-east-1';
            if (preg_match('/>(.+?)<\\/LocationConstraint>/', $response->getBody(), $matches)) {
                $location = $matches[1] === 'EU' ? 'eu-west-1' : $matches[1];
            }
            $result['LocationConstraint'] = $location;
        }
        return $result;
    }
    public function parseMemberFromStream(\UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface $stream, \UglyRobot\Infinite_Uploads\Aws\Api\StructureShape $member, $response)
    {
        return $this->parser->parseMemberFromStream($stream, $member, $response);
    }
}
