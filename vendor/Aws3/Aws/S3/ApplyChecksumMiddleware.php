<?php

namespace UglyRobot\Infinite_Uploads\Aws\S3;

use UglyRobot\Infinite_Uploads\Aws\Api\ApiProvider;
use UglyRobot\Infinite_Uploads\Aws\Api\Service;
use UglyRobot\Infinite_Uploads\Aws\CommandInterface;
use UglyRobot\Infinite_Uploads\GuzzleHttp\Psr7;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\RequestInterface;
/**
 * Apply required or optional MD5s to requests before sending.
 *
 * IMPORTANT: This middleware must be added after the "build" step.
 *
 * @internal
 */
class ApplyChecksumMiddleware
{
    private static $sha256 = ['PutObject', 'UploadPart'];
    /** @var Service */
    private $api;
    private $nextHandler;
    /**
     * Create a middleware wrapper function.
     *
     * @param Service $api
     * @return callable
     */
    public static function wrap(\UglyRobot\Infinite_Uploads\Aws\Api\Service $api)
    {
        return function (callable $handler) use($api) {
            return new self($handler, $api);
        };
    }
    public function __construct(callable $nextHandler, \UglyRobot\Infinite_Uploads\Aws\Api\Service $api)
    {
        $this->api = $api;
        $this->nextHandler = $nextHandler;
    }
    public function __invoke(\UglyRobot\Infinite_Uploads\Aws\CommandInterface $command, \UglyRobot\Infinite_Uploads\Psr\Http\Message\RequestInterface $request)
    {
        $next = $this->nextHandler;
        $name = $command->getName();
        $body = $request->getBody();
        $op = $this->api->getOperation($command->getName());
        if (!empty($op['httpChecksumRequired']) && !$request->hasHeader('Content-MD5')) {
            // Set the content MD5 header for operations that require it.
            $request = $request->withHeader('Content-MD5', base64_encode(\UglyRobot\Infinite_Uploads\GuzzleHttp\Psr7\hash($body, 'md5', true)));
        } elseif (in_array($name, self::$sha256) && $command['ContentSHA256']) {
            // Set the content hash header if provided in the parameters.
            $request = $request->withHeader('X-Amz-Content-Sha256', $command['ContentSHA256']);
        }
        return $next($command, $request);
    }
}
