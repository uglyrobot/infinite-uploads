<?php

namespace UglyRobot\Infinite_Uploads\Aws\Arn\S3;

use UglyRobot\Infinite_Uploads\Aws\Arn\ArnInterface;
/**
 * @internal
 */
interface BucketArnInterface extends ArnInterface
{
    public function getBucketName();
}
