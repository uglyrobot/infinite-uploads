<?php

namespace UglyRobot\Infinite_Uploads\Aws\ClientSideMonitoring\Exception;

use UglyRobot\Infinite_Uploads\Aws\HasMonitoringEventsTrait;
use UglyRobot\Infinite_Uploads\Aws\MonitoringEventsInterface;
/**
 * Represents an error interacting with configuration for client-side monitoring.
 */
class ConfigurationException extends \RuntimeException implements \UglyRobot\Infinite_Uploads\Aws\MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
