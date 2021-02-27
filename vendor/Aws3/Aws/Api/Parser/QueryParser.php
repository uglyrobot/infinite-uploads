<?php

namespace UglyRobot\Infinite_Uploads\Aws\Api\Parser;

use UglyRobot\Infinite_Uploads\Aws\Api\Service;
use UglyRobot\Infinite_Uploads\Aws\Api\StructureShape;
use UglyRobot\Infinite_Uploads\Aws\Result;
use UglyRobot\Infinite_Uploads\Aws\CommandInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface;
/**
 * @internal Parses query (XML) responses (e.g., EC2, SQS, and many others)
 */
class QueryParser extends \UglyRobot\Infinite_Uploads\Aws\Api\Parser\AbstractParser
{
    use PayloadParserTrait;
    /** @var bool */
    private $honorResultWrapper;
    /**
     * @param Service   $api                Service description
     * @param XmlParser $xmlParser          Optional XML parser
     * @param bool      $honorResultWrapper Set to false to disable the peeling
     *                                      back of result wrappers from the
     *                                      output structure.
     */
    public function __construct(\UglyRobot\Infinite_Uploads\Aws\Api\Service $api, \UglyRobot\Infinite_Uploads\Aws\Api\Parser\XmlParser $xmlParser = null, $honorResultWrapper = true)
    {
        parent::__construct($api);
        $this->parser = $xmlParser ?: new \UglyRobot\Infinite_Uploads\Aws\Api\Parser\XmlParser();
        $this->honorResultWrapper = $honorResultWrapper;
    }
    public function __invoke(\UglyRobot\Infinite_Uploads\Aws\CommandInterface $command, \UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface $response)
    {
        $output = $this->api->getOperation($command->getName())->getOutput();
        $xml = $this->parseXml($response->getBody(), $response);
        if ($this->honorResultWrapper && $output['resultWrapper']) {
            $xml = $xml->{$output['resultWrapper']};
        }
        return new \UglyRobot\Infinite_Uploads\Aws\Result($this->parser->parse($output, $xml));
    }
    public function parseMemberFromStream(\UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface $stream, \UglyRobot\Infinite_Uploads\Aws\Api\StructureShape $member, $response)
    {
        $xml = $this->parseXml($stream, $response);
        return $this->parser->parse($member, $xml);
    }
}
