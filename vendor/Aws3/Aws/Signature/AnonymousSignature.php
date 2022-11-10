<?php

namespace UglyRobot\Infinite_Uploads\Aws\Signature;

use UglyRobot\Infinite_Uploads\Aws\Credentials\CredentialsInterface;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\RequestInterface;
/**
 * Provides anonymous client access (does not sign requests).
 */
class AnonymousSignature implements \UglyRobot\Infinite_Uploads\Aws\Signature\SignatureInterface
{
    /**
     * /** {@inheritdoc}
     */
    public function signRequest(\UglyRobot\Infinite_Uploads\Psr\Http\Message\RequestInterface $request, \UglyRobot\Infinite_Uploads\Aws\Credentials\CredentialsInterface $credentials)
    {
        return $request;
    }
    /**
     * /** {@inheritdoc}
     */
    public function presign(\UglyRobot\Infinite_Uploads\Psr\Http\Message\RequestInterface $request, \UglyRobot\Infinite_Uploads\Aws\Credentials\CredentialsInterface $credentials, $expires, array $options = [])
    {
        return $request;
    }
}
