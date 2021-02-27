<?php

namespace UglyRobot\Infinite_Uploads\Aws\Retry\Exception;

use UglyRobot\Infinite_Uploads\Aws\HasMonitoringEventsTrait;
use UglyRobot\Infinite_Uploads\Aws\MonitoringEventsInterface;
/**
 * Represents an error interacting with retry configuration
 */
class ConfigurationException extends \RuntimeException implements \UglyRobot\Infinite_Uploads\Aws\MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
