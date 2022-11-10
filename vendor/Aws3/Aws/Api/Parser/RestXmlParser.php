<?php

namespace UglyRobot\Infinite_Uploads\Aws\Api\Parser;

use UglyRobot\Infinite_Uploads\Aws\Api\StructureShape;
use UglyRobot\Infinite_Uploads\Aws\Api\Service;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface;
/**
 * @internal Implements REST-XML parsing (e.g., S3, CloudFront, etc...)
 */
class RestXmlParser extends \UglyRobot\Infinite_Uploads\Aws\Api\Parser\AbstractRestParser
{
    use PayloadParserTrait;
    /**
     * @param Service   $api    Service description
     * @param XmlParser $parser XML body parser
     */
    public function __construct(\UglyRobot\Infinite_Uploads\Aws\Api\Service $api, \UglyRobot\Infinite_Uploads\Aws\Api\Parser\XmlParser $parser = null)
    {
        parent::__construct($api);
        $this->parser = $parser ?: new \UglyRobot\Infinite_Uploads\Aws\Api\Parser\XmlParser();
    }
    protected function payload(\UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface $response, \UglyRobot\Infinite_Uploads\Aws\Api\StructureShape $member, array &$result)
    {
        $result += $this->parseMemberFromStream($response->getBody(), $member, $response);
    }
    public function parseMemberFromStream(\UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface $stream, \UglyRobot\Infinite_Uploads\Aws\Api\StructureShape $member, $response)
    {
        $xml = $this->parseXml($stream, $response);
        return $this->parser->parse($member, $xml);
    }
}
