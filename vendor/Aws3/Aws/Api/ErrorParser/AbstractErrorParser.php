<?php

namespace UglyRobot\Infinite_Uploads\Aws\Api\ErrorParser;

use UglyRobot\Infinite_Uploads\Aws\Api\Parser\MetadataParserTrait;
use UglyRobot\Infinite_Uploads\Aws\Api\Parser\PayloadParserTrait;
use UglyRobot\Infinite_Uploads\Aws\Api\Service;
use UglyRobot\Infinite_Uploads\Aws\Api\StructureShape;
use UglyRobot\Infinite_Uploads\Aws\CommandInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface;
abstract class AbstractErrorParser
{
    use MetadataParserTrait;
    use PayloadParserTrait;
    /**
     * @var Service
     */
    protected $api;
    /**
     * @param Service $api
     */
    public function __construct(\UglyRobot\Infinite_Uploads\Aws\Api\Service $api = null)
    {
        $this->api = $api;
    }
    protected abstract function payload(\UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface $response, \UglyRobot\Infinite_Uploads\Aws\Api\StructureShape $member);
    protected function extractPayload(\UglyRobot\Infinite_Uploads\Aws\Api\StructureShape $member, \UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface $response)
    {
        if ($member instanceof StructureShape) {
            // Structure members parse top-level data into a specific key.
            return $this->payload($response, $member);
        } else {
            // Streaming data is just the stream from the response body.
            return $response->getBody();
        }
    }
    protected function populateShape(array &$data, \UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface $response, \UglyRobot\Infinite_Uploads\Aws\CommandInterface $command = null)
    {
        $data['body'] = [];
        if (!empty($command) && !empty($this->api)) {
            // If modeled error code is indicated, check for known error shape
            if (!empty($data['code'])) {
                $errors = $this->api->getOperation($command->getName())->getErrors();
                foreach ($errors as $key => $error) {
                    // If error code matches a known error shape, populate the body
                    if ($data['code'] == $error['name'] && $error instanceof StructureShape) {
                        $modeledError = $error;
                        $data['body'] = $this->extractPayload($modeledError, $response);
                        $data['error_shape'] = $modeledError;
                        foreach ($error->getMembers() as $name => $member) {
                            switch ($member['location']) {
                                case 'header':
                                    $this->extractHeader($name, $member, $response, $data['body']);
                                    break;
                                case 'headers':
                                    $this->extractHeaders($name, $member, $response, $data['body']);
                                    break;
                                case 'statusCode':
                                    $this->extractStatus($name, $response, $data['body']);
                                    break;
                            }
                        }
                        break;
                    }
                }
            }
        }
        return $data;
    }
}
