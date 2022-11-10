<?php

namespace UglyRobot\Infinite_Uploads\Aws\Api\Parser;

use UglyRobot\Infinite_Uploads\Aws\Api\Service;
use UglyRobot\Infinite_Uploads\Aws\Api\StructureShape;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface;
/**
 * @internal Implements REST-JSON parsing (e.g., Glacier, Elastic Transcoder)
 */
class RestJsonParser extends \UglyRobot\Infinite_Uploads\Aws\Api\Parser\AbstractRestParser
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
    protected function payload(\UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface $response, \UglyRobot\Infinite_Uploads\Aws\Api\StructureShape $member, array &$result)
    {
        $jsonBody = $this->parseJson($response->getBody(), $response);
        if ($jsonBody) {
            $result += $this->parser->parse($member, $jsonBody);
        }
    }
    public function parseMemberFromStream(\UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface $stream, \UglyRobot\Infinite_Uploads\Aws\Api\StructureShape $member, $response)
    {
        $jsonBody = $this->parseJson($stream, $response);
        if ($jsonBody) {
            return $this->parser->parse($member, $jsonBody);
        }
        return [];
    }
}
