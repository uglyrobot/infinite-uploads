<?php

namespace UglyRobot\Infinite_Uploads\Aws\Api\Serializer;

use UglyRobot\Infinite_Uploads\Aws\Api\Service;
use UglyRobot\Infinite_Uploads\Aws\Api\StructureShape;
/**
 * Serializes requests for the REST-JSON protocol.
 * @internal
 */
class RestJsonSerializer extends \UglyRobot\Infinite_Uploads\Aws\Api\Serializer\RestSerializer
{
    /** @var JsonBody */
    private $jsonFormatter;
    /** @var string */
    private $contentType;
    /**
     * @param Service  $api           Service API description
     * @param string   $endpoint      Endpoint to connect to
     * @param JsonBody $jsonFormatter Optional JSON formatter to use
     */
    public function __construct(\UglyRobot\Infinite_Uploads\Aws\Api\Service $api, $endpoint, \UglyRobot\Infinite_Uploads\Aws\Api\Serializer\JsonBody $jsonFormatter = null)
    {
        parent::__construct($api, $endpoint);
        $this->contentType = 'application/json';
        $this->jsonFormatter = $jsonFormatter ?: new \UglyRobot\Infinite_Uploads\Aws\Api\Serializer\JsonBody($api);
    }
    protected function payload(\UglyRobot\Infinite_Uploads\Aws\Api\StructureShape $member, array $value, array &$opts)
    {
        $opts['headers']['Content-Type'] = $this->contentType;
        $opts['body'] = (string) $this->jsonFormatter->build($member, $value);
    }
}
