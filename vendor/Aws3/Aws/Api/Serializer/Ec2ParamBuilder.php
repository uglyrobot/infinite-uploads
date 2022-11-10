<?php

namespace UglyRobot\Infinite_Uploads\Aws\Api\Serializer;

use UglyRobot\Infinite_Uploads\Aws\Api\Shape;
use UglyRobot\Infinite_Uploads\Aws\Api\ListShape;
/**
 * @internal
 */
class Ec2ParamBuilder extends \UglyRobot\Infinite_Uploads\Aws\Api\Serializer\QueryParamBuilder
{
    protected function queryName(\UglyRobot\Infinite_Uploads\Aws\Api\Shape $shape, $default = null)
    {
        return $shape['queryName'] ?: ucfirst($shape['locationName']) ?: $default;
    }
    protected function isFlat(\UglyRobot\Infinite_Uploads\Aws\Api\Shape $shape)
    {
        return false;
    }
    protected function format_list(\UglyRobot\Infinite_Uploads\Aws\Api\ListShape $shape, array $value, $prefix, &$query)
    {
        // Handle empty list serialization
        if (!$value) {
            $query[$prefix] = false;
        } else {
            $items = $shape->getMember();
            foreach ($value as $k => $v) {
                $this->format($items, $v, $prefix . '.' . ($k + 1), $query);
            }
        }
    }
}
