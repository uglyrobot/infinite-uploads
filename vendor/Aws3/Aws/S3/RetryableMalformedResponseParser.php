<?php

namespace UglyRobot\Infinite_Uploads\Aws\S3;

use UglyRobot\Infinite_Uploads\Aws\Api\Parser\AbstractParser;
use UglyRobot\Infinite_Uploads\Aws\Api\StructureShape;
use UglyRobot\Infinite_Uploads\Aws\Api\Parser\Exception\ParserException;
use UglyRobot\Infinite_Uploads\Aws\CommandInterface;
use UglyRobot\Infinite_Uploads\Aws\Exception\AwsException;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface;
/**
 * Converts malformed responses to a retryable error type.
 *
 * @internal
 */
class RetryableMalformedResponseParser extends \UglyRobot\Infinite_Uploads\Aws\Api\Parser\AbstractParser
{
    /** @var string */
    private $exceptionClass;
    public function __construct(callable $parser, $exceptionClass = \UglyRobot\Infinite_Uploads\Aws\Exception\AwsException::class)
    {
        $this->parser = $parser;
        $this->exceptionClass = $exceptionClass;
    }
    public function __invoke(\UglyRobot\Infinite_Uploads\Aws\CommandInterface $command, \UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface $response)
    {
        $fn = $this->parser;
        try {
            return $fn($command, $response);
        } catch (ParserException $e) {
            throw new $this->exceptionClass("Error parsing response for {$command->getName()}:" . " AWS parsing error: {$e->getMessage()}", $command, ['connection_error' => true, 'exception' => $e], $e);
        }
    }
    public function parseMemberFromStream(\UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface $stream, \UglyRobot\Infinite_Uploads\Aws\Api\StructureShape $member, $response)
    {
        return $this->parser->parseMemberFromStream($stream, $member, $response);
    }
}
