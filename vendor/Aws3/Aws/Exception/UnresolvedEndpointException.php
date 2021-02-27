<?php

namespace UglyRobot\Infinite_Uploads\Aws\Exception;

use UglyRobot\Infinite_Uploads\Aws\HasMonitoringEventsTrait;
use UglyRobot\Infinite_Uploads\Aws\MonitoringEventsInterface;
class UnresolvedEndpointException extends \RuntimeException implements \UglyRobot\Infinite_Uploads\Aws\MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
