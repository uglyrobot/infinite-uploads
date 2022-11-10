<?php

namespace UglyRobot\Infinite_Uploads\Aws\Arn\S3;

use UglyRobot\Infinite_Uploads\Aws\Arn\AccessPointArn as BaseAccessPointArn;
use UglyRobot\Infinite_Uploads\Aws\Arn\AccessPointArnInterface;
use UglyRobot\Infinite_Uploads\Aws\Arn\ArnInterface;
use UglyRobot\Infinite_Uploads\Aws\Arn\Exception\InvalidArnException;
/**
 * @internal
 */
class AccessPointArn extends \UglyRobot\Infinite_Uploads\Aws\Arn\AccessPointArn implements \UglyRobot\Infinite_Uploads\Aws\Arn\AccessPointArnInterface
{
    /**
     * Validation specific to AccessPointArn
     *
     * @param array $data
     */
    protected static function validate(array $data)
    {
        parent::validate($data);
        if ($data['service'] !== 's3') {
            throw new \UglyRobot\Infinite_Uploads\Aws\Arn\Exception\InvalidArnException("The 3rd component of an S3 access" . " point ARN represents the region and must be 's3'.");
        }
    }
}
