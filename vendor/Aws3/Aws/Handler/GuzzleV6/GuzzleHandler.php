<?php

namespace UglyRobot\Infinite_Uploads\Aws\Handler\GuzzleV6;

use Exception;
use UglyRobot\Infinite_Uploads\GuzzleHttp\Exception\ConnectException;
use UglyRobot\Infinite_Uploads\GuzzleHttp\Exception\RequestException;
use UglyRobot\Infinite_Uploads\GuzzleHttp\Promise;
use UglyRobot\Infinite_Uploads\GuzzleHttp\Client;
use UglyRobot\Infinite_Uploads\GuzzleHttp\ClientInterface;
use UglyRobot\Infinite_Uploads\GuzzleHttp\TransferStats;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\RequestInterface as Psr7Request;
/**
 * A request handler that sends PSR-7-compatible requests with Guzzle 6.
 */
class GuzzleHandler
{
    /** @var ClientInterface */
    private $client;
    /**
     * @param ClientInterface $client
     */
    public function __construct(\UglyRobot\Infinite_Uploads\GuzzleHttp\ClientInterface $client = null)
    {
        $this->client = $client ?: new \UglyRobot\Infinite_Uploads\GuzzleHttp\Client();
    }
    /**
     * @param Psr7Request $request
     * @param array       $options
     *
     * @return Promise\Promise
     */
    public function __invoke(\UglyRobot\Infinite_Uploads\Psr\Http\Message\RequestInterface $request, array $options = [])
    {
        $request = $request->withHeader('User-Agent', $request->getHeaderLine('User-Agent') . ' ' . \UglyRobot\Infinite_Uploads\GuzzleHttp\default_user_agent());
        return $this->client->sendAsync($request, $this->parseOptions($options))->otherwise(static function ($e) {
            $error = ['exception' => $e, 'connection_error' => $e instanceof ConnectException, 'response' => null];
            if ($e instanceof RequestException && $e->getResponse()) {
                $error['response'] = $e->getResponse();
            } else {
                if (class_exists('Error') && $e instanceof \Error && $e->getResponse()) {
                    $error['response'] = $e->getResponse();
                }
            }
            return new \UglyRobot\Infinite_Uploads\GuzzleHttp\Promise\RejectedPromise($error);
        });
    }
    private function parseOptions(array $options)
    {
        if (isset($options['http_stats_receiver'])) {
            $fn = $options['http_stats_receiver'];
            unset($options['http_stats_receiver']);
            $prev = isset($options['on_stats']) ? $options['on_stats'] : null;
            $options['on_stats'] = static function (\UglyRobot\Infinite_Uploads\GuzzleHttp\TransferStats $stats) use($fn, $prev) {
                if (is_callable($prev)) {
                    $prev($stats);
                }
                $transferStats = ['total_time' => $stats->getTransferTime()];
                $transferStats += $stats->getHandlerStats();
                $fn($transferStats);
            };
        }
        return $options;
    }
}
