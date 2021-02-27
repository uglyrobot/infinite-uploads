<?php

namespace UglyRobot\Infinite_Uploads\Aws\Api\ErrorParser;

use UglyRobot\Infinite_Uploads\Aws\Api\Parser\JsonParser;
use UglyRobot\Infinite_Uploads\Aws\Api\Service;
use UglyRobot\Infinite_Uploads\Aws\CommandInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface;
/**
 * Parsers JSON-RPC errors.
 */
class JsonRpcErrorParser extends \UglyRobot\Infinite_Uploads\Aws\Api\ErrorParser\AbstractErrorParser
{
    use JsonParserTrait;
    private $parser;
    public function __construct(\UglyRobot\Infinite_Uploads\Aws\Api\Service $api = null, \UglyRobot\Infinite_Uploads\Aws\Api\Parser\JsonParser $parser = null)
    {
        parent::__construct($api);
        $this->parser = $parser ?: new \UglyRobot\Infinite_Uploads\Aws\Api\Parser\JsonParser();
    }
    public function __invoke(\UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface $response, \UglyRobot\Infinite_Uploads\Aws\CommandInterface $command = null)
    {
        $data = $this->genericHandler($response);
        // Make the casing consistent across services.
        if ($data['parsed']) {
            $data['parsed'] = array_change_key_case($data['parsed']);
        }
        if (isset($data['parsed']['__type'])) {
            $parts = explode('#', $data['parsed']['__type']);
            $data['code'] = isset($parts[1]) ? $parts[1] : $parts[0];
            $data['message'] = isset($data['parsed']['message']) ? $data['parsed']['message'] : null;
        }
        $this->populateShape($data, $response, $command);
        return $data;
    }
}
