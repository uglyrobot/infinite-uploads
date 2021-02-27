<?php

namespace UglyRobot\Infinite_Uploads\Aws\S3;

use UglyRobot\Infinite_Uploads\Aws\CommandInterface;
use UglyRobot\Infinite_Uploads\Aws\ResultInterface;
use UglyRobot\Infinite_Uploads\Aws\S3\Exception\PermanentRedirectException;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\RequestInterface;
/**
 * Throws a PermanentRedirectException exception when a 301 redirect is
 * encountered.
 *
 * @internal
 */
class PermanentRedirectMiddleware
{
    /** @var callable  */
    private $nextHandler;
    /**
     * Create a middleware wrapper function.
     *
     * @return callable
     */
    public static function wrap()
    {
        return function (callable $handler) {
            return new self($handler);
        };
    }
    /**
     * @param callable $nextHandler Next handler to invoke.
     */
    public function __construct(callable $nextHandler)
    {
        $this->nextHandler = $nextHandler;
    }
    public function __invoke(\UglyRobot\Infinite_Uploads\Aws\CommandInterface $command, \UglyRobot\Infinite_Uploads\Psr\Http\Message\RequestInterface $request = null)
    {
        $next = $this->nextHandler;
        return $next($command, $request)->then(function (\UglyRobot\Infinite_Uploads\Aws\ResultInterface $result) use($command) {
            $status = isset($result['@metadata']['statusCode']) ? $result['@metadata']['statusCode'] : null;
            if ($status == 301) {
                throw new \UglyRobot\Infinite_Uploads\Aws\S3\Exception\PermanentRedirectException('Encountered a permanent redirect while requesting ' . $result->search('"@metadata".effectiveUri') . '. ' . 'Are you sure you are using the correct region for ' . 'this bucket?', $command, ['result' => $result]);
            }
            return $result;
        });
    }
}
