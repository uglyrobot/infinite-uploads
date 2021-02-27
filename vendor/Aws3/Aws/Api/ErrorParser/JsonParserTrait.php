<?php

namespace UglyRobot\Infinite_Uploads\Aws\Api\ErrorParser;

use UglyRobot\Infinite_Uploads\Aws\Api\Parser\PayloadParserTrait;
use UglyRobot\Infinite_Uploads\Aws\Api\StructureShape;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface;
/**
 * Provides basic JSON error parsing functionality.
 */
trait JsonParserTrait
{
    use PayloadParserTrait;
    private function genericHandler(\UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface $response)
    {
        $code = (string) $response->getStatusCode();
        return ['request_id' => (string) $response->getHeaderLine('x-amzn-requestid'), 'code' => null, 'message' => null, 'type' => $code[0] == '4' ? 'client' : 'server', 'parsed' => $this->parseJson($response->getBody(), $response)];
    }
    protected function payload(\UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface $response, \UglyRobot\Infinite_Uploads\Aws\Api\StructureShape $member)
    {
        $jsonBody = $this->parseJson($response->getBody(), $response);
        if ($jsonBody) {
            return $this->parser->parse($member, $jsonBody);
        }
    }
}
