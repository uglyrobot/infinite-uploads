<?php

namespace UglyRobot\Infinite_Uploads\Aws\Exception;

use UglyRobot\Infinite_Uploads\Aws\HasMonitoringEventsTrait;
use UglyRobot\Infinite_Uploads\Aws\MonitoringEventsInterface;
class CredentialsException extends \RuntimeException implements \UglyRobot\Infinite_Uploads\Aws\MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
