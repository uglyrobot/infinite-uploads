<?php

namespace UglyRobot\Infinite_Uploads\Aws\S3\RegionalEndpoint\Exception;

use UglyRobot\Infinite_Uploads\Aws\HasMonitoringEventsTrait;
use UglyRobot\Infinite_Uploads\Aws\MonitoringEventsInterface;
/**
 * Represents an error interacting with configuration for sts regional endpoints
 */
class ConfigurationException extends \RuntimeException implements \UglyRobot\Infinite_Uploads\Aws\MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
