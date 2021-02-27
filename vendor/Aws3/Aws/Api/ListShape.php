<?php

namespace UglyRobot\Infinite_Uploads\Aws\Api;

/**
 * Represents a list shape.
 */
class ListShape extends \UglyRobot\Infinite_Uploads\Aws\Api\Shape
{
    private $member;
    public function __construct(array $definition, \UglyRobot\Infinite_Uploads\Aws\Api\ShapeMap $shapeMap)
    {
        $definition['type'] = 'list';
        parent::__construct($definition, $shapeMap);
    }
    /**
     * @return Shape
     * @throws \RuntimeException if no member is specified
     */
    public function getMember()
    {
        if (!$this->member) {
            if (!isset($this->definition['member'])) {
                throw new \RuntimeException('No member attribute specified');
            }
            $this->member = \UglyRobot\Infinite_Uploads\Aws\Api\Shape::create($this->definition['member'], $this->shapeMap);
        }
        return $this->member;
    }
}
