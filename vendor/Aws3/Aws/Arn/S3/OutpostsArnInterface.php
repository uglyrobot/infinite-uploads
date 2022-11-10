<?php

namespace UglyRobot\Infinite_Uploads\Aws\Arn\S3;

use UglyRobot\Infinite_Uploads\Aws\Arn\ArnInterface;
/**
 * @internal
 */
interface OutpostsArnInterface extends ArnInterface
{
    public function getOutpostId();
}
