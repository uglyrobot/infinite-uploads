<?php

namespace UglyRobot\Infinite_Uploads\Aws\Api\Parser;

use UglyRobot\Infinite_Uploads\Aws\Api\Service;
use UglyRobot\Infinite_Uploads\Aws\Api\StructureShape;
use UglyRobot\Infinite_Uploads\Aws\CommandInterface;
use UglyRobot\Infinite_Uploads\Aws\ResultInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface;
/**
 * @internal
 */
abstract class AbstractParser
{
    /** @var \Aws\Api\Service Representation of the service API*/
    protected $api;
    /** @var callable */
    protected $parser;
    /**
     * @param Service $api Service description.
     */
    public function __construct(\UglyRobot\Infinite_Uploads\Aws\Api\Service $api)
    {
        $this->api = $api;
    }
    /**
     * @param CommandInterface  $command  Command that was executed.
     * @param ResponseInterface $response Response that was received.
     *
     * @return ResultInterface
     */
    public abstract function __invoke(\UglyRobot\Infinite_Uploads\Aws\CommandInterface $command, \UglyRobot\Infinite_Uploads\Psr\Http\Message\ResponseInterface $response);
    public abstract function parseMemberFromStream(\UglyRobot\Infinite_Uploads\Psr\Http\Message\StreamInterface $stream, \UglyRobot\Infinite_Uploads\Aws\Api\StructureShape $member, $response);
}
