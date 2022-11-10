<?php

namespace UglyRobot\Infinite_Uploads\GuzzleHttp\Promise;

final class Is
{
    /**
     * Returns true if a promise is pending.
     *
     * @return bool
     */
    public static function pending(\UglyRobot\Infinite_Uploads\GuzzleHttp\Promise\PromiseInterface $promise)
    {
        return $promise->getState() === \UglyRobot\Infinite_Uploads\GuzzleHttp\Promise\PromiseInterface::PENDING;
    }
    /**
     * Returns true if a promise is fulfilled or rejected.
     *
     * @return bool
     */
    public static function settled(\UglyRobot\Infinite_Uploads\GuzzleHttp\Promise\PromiseInterface $promise)
    {
        return $promise->getState() !== \UglyRobot\Infinite_Uploads\GuzzleHttp\Promise\PromiseInterface::PENDING;
    }
    /**
     * Returns true if a promise is fulfilled.
     *
     * @return bool
     */
    public static function fulfilled(\UglyRobot\Infinite_Uploads\GuzzleHttp\Promise\PromiseInterface $promise)
    {
        return $promise->getState() === \UglyRobot\Infinite_Uploads\GuzzleHttp\Promise\PromiseInterface::FULFILLED;
    }
    /**
     * Returns true if a promise is rejected.
     *
     * @return bool
     */
    public static function rejected(\UglyRobot\Infinite_Uploads\GuzzleHttp\Promise\PromiseInterface $promise)
    {
        return $promise->getState() === \UglyRobot\Infinite_Uploads\GuzzleHttp\Promise\PromiseInterface::REJECTED;
    }
}
