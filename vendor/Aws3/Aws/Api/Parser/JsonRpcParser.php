<?php

namespace UglyRobot\Infinite_Uploads\Aws\Api\Parser;

use UglyRobot\Infinite_Uploads\Aws\Api\StructureShape;
use UglyRobot\Infinite_Uploads\Aws\Api\Service;
use UglyRobot\Infinite_Uploads\Aws\Result;
use UglyRobot\Infinite_Uploads\Aws\CommandInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface;
/**
 * @internal Implements JSON-RPC parsing (e.g., DynamoDB)
 */
class JsonRpcParser extends \UglyRobot\Infinite_Uploads\Aws\Api\Parser\AbstractParser
{
    use PayloadParserTrait;
    /**
     * @param Service    $api    Service description
     * @param JsonParser $parser JSON body builder
     */
    public function __construct(\UglyRobot\Infinite_Uploads\Aws\Api\Service $api, \UglyRobot\Infinite_Uploads\Aws\Api\Parser\JsonParser $parser = null)
    {
        parent::__construct($api);
        $this->parser = $parser ?: new \UglyRobot\Infinite_Uploads\Aws\Api\Parser\JsonParser();
    }
    public function __invoke(\UglyRobot\Infinite_Uploads\Aws\CommandInterface $command, \UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface $response)
    {
        $operation = $this->api->getOperation($command->getName());
        $result = null === $operation['output'] ? null : $this->parseMemberFromStream($response->getBody(), $operation->getOutput(), $response);
        return new \UglyRobot\Infinite_Uploads\Aws\Result($result ?: []);
    }
    public function parseMemberFromStream(\UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface $stream, \UglyRobot\Infinite_Uploads\Aws\Api\StructureShape $member, $response)
    {
        return $this->parser->parse($member, $this->parseJson($stream, $response));
    }
}
