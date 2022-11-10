<?php

namespace UglyRobot\Infinite_Uploads\Aws\Api;

/**
 * Base class representing a modeled shape.
 */
class Shape extends \UglyRobot\Infinite_Uploads\Aws\Api\AbstractModel
{
    /**
     * Get a concrete shape for the given definition.
     *
     * @param array    $definition
     * @param ShapeMap $shapeMap
     *
     * @return mixed
     * @throws \RuntimeException if the type is invalid
     */
    public static function create(array $definition, \UglyRobot\Infinite_Uploads\Aws\Api\ShapeMap $shapeMap)
    {
        static $map = ['structure' => 'UglyRobot\\Infinite_Uploads\\Aws\\Api\\StructureShape', 'map' => 'UglyRobot\\Infinite_Uploads\\Aws\\Api\\MapShape', 'list' => 'UglyRobot\\Infinite_Uploads\\Aws\\Api\\ListShape', 'timestamp' => 'UglyRobot\\Infinite_Uploads\\Aws\\Api\\TimestampShape', 'integer' => 'UglyRobot\\Infinite_Uploads\\Aws\\Api\\Shape', 'double' => 'UglyRobot\\Infinite_Uploads\\Aws\\Api\\Shape', 'float' => 'UglyRobot\\Infinite_Uploads\\Aws\\Api\\Shape', 'long' => 'UglyRobot\\Infinite_Uploads\\Aws\\Api\\Shape', 'string' => 'UglyRobot\\Infinite_Uploads\\Aws\\Api\\Shape', 'byte' => 'UglyRobot\\Infinite_Uploads\\Aws\\Api\\Shape', 'character' => 'UglyRobot\\Infinite_Uploads\\Aws\\Api\\Shape', 'blob' => 'UglyRobot\\Infinite_Uploads\\Aws\\Api\\Shape', 'boolean' => 'UglyRobot\\Infinite_Uploads\\Aws\\Api\\Shape'];
        if (isset($definition['shape'])) {
            return $shapeMap->resolve($definition);
        }
        if (!isset($map[$definition['type']])) {
            throw new \RuntimeException('Invalid type: ' . print_r($definition, true));
        }
        $type = $map[$definition['type']];
        return new $type($definition, $shapeMap);
    }
    /**
     * Get the type of the shape
     *
     * @return string
     */
    public function getType()
    {
        return $this->definition['type'];
    }
    /**
     * Get the name of the shape
     *
     * @return string
     */
    public function getName()
    {
        return $this->definition['name'];
    }
}
