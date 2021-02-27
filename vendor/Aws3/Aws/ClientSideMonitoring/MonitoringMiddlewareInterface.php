<?php

namespace UglyRobot\Infinite_Uploads\Aws\ClientSideMonitoring;

use UglyRobot\Infinite_Uploads\Aws\CommandInterface;
use UglyRobot\Infinite_Uploads\Aws\Exception\AwsException;
use UglyRobot\Infinite_Uploads\Aws\ResultInterface;
use UglyRobot\Infinite_Uploads\GuzzleHttp\Psr7\Request;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\RequestInterface;
/**
 * @internal
 */
interface MonitoringMiddlewareInterface
{
    /**
     * Data for event properties to be sent to the monitoring agent.
     *
     * @param RequestInterface $request
     * @return array
     */
    public static function getRequestData(\UglyRobot\Infinite_Uploads\Psr\Http\Message\RequestInterface $request);
    /**
     * Data for event properties to be sent to the monitoring agent.
     *
     * @param ResultInterface|AwsException|\Exception $klass
     * @return array
     */
    public static function getResponseData($klass);
    public function __invoke(\UglyRobot\Infinite_Uploads\Aws\CommandInterface $cmd, \UglyRobot\Infinite_Uploads\Psr\Http\Message\RequestInterface $request);
}
