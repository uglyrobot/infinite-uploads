<?php

namespace UglyRobot\Infinite_Uploads\Aws\S3\UseArnRegion\Exception;

use UglyRobot\Infinite_Uploads\Aws\HasMonitoringEventsTrait;
use UglyRobot\Infinite_Uploads\Aws\MonitoringEventsInterface;
/**
 * Represents an error interacting with configuration for S3's UseArnRegion
 */
class ConfigurationException extends \RuntimeException implements \UglyRobot\Infinite_Uploads\Aws\MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
