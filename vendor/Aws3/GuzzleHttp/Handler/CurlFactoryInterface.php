<?php

namespace UglyRobot\Infinite_Uploads\GuzzleHttp\Handler;

use UglyRobot\Infinite_Uploads\Psr\Http\Message\RequestInterface;
interface CurlFactoryInterface
{
    /**
     * Creates a cURL handle resource.
     *
     * @param RequestInterface $request Request
     * @param array            $options Transfer options
     *
     * @return EasyHandle
     * @throws \RuntimeException when an option cannot be applied
     */
    public function create(\UglyRobot\Infinite_Uploads\Psr\Http\Message\RequestInterface $request, array $options);
    /**
     * Release an easy handle, allowing it to be reused or closed.
     *
     * This function must call unset on the easy handle's "handle" property.
     *
     * @param EasyHandle $easy
     */
    public function release(\UglyRobot\Infinite_Uploads\GuzzleHttp\Handler\EasyHandle $easy);
}
