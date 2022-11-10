<?php

namespace UglyRobot\Infinite_Uploads\Aws\Api\Parser;

use UglyRobot\Infinite_Uploads\Aws\Api\DateTimeResult;
use UglyRobot\Infinite_Uploads\Aws\Api\Shape;
/**
 * @internal Implements standard JSON parsing.
 */
class JsonParser
{
    public function parse(\UglyRobot\Infinite_Uploads\Aws\Api\Shape $shape, $value)
    {
        if ($value === null) {
            return $value;
        }
        switch ($shape['type']) {
            case 'structure':
                $target = [];
                foreach ($shape->getMembers() as $name => $member) {
                    $locationName = $member['locationName'] ?: $name;
                    if (isset($value[$locationName])) {
                        $target[$name] = $this->parse($member, $value[$locationName]);
                    }
                }
                return $target;
            case 'list':
                $member = $shape->getMember();
                $target = [];
                foreach ($value as $v) {
                    $target[] = $this->parse($member, $v);
                }
                return $target;
            case 'map':
                $values = $shape->getValue();
                $target = [];
                foreach ($value as $k => $v) {
                    $target[$k] = $this->parse($values, $v);
                }
                return $target;
            case 'timestamp':
                return \UglyRobot\Infinite_Uploads\Aws\Api\DateTimeResult::fromTimestamp($value, !empty($shape['timestampFormat']) ? $shape['timestampFormat'] : null);
            case 'blob':
                return base64_decode($value);
            default:
                return $value;
        }
    }
}
