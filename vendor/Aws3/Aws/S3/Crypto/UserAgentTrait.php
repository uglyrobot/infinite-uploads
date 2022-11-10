<?php

namespace UglyRobot\Infinite_Uploads\Aws\S3\Crypto;

use UglyRobot\Infinite_Uploads\Aws\AwsClientInterface;
use UglyRobot\Infinite_Uploads\Aws\Middleware;
use UglyRobot\Infinite_Uploads\Psr\Http\Message\RequestInterface;
trait UserAgentTrait
{
    private function appendUserAgent(\UglyRobot\Infinite_Uploads\Aws\AwsClientInterface $client, $agentString)
    {
        $list = $client->getHandlerList();
        $list->appendBuild(\UglyRobot\Infinite_Uploads\Aws\Middleware::mapRequest(function (\UglyRobot\Infinite_Uploads\Psr\Http\Message\RequestInterface $req) use($agentString) {
            if (!empty($req->getHeader('User-Agent')) && !empty($req->getHeader('User-Agent')[0])) {
                $userAgent = $req->getHeader('User-Agent')[0];
                if (strpos($userAgent, $agentString) === false) {
                    $userAgent .= " {$agentString}";
                }
            } else {
                $userAgent = $agentString;
            }
            $req = $req->withHeader('User-Agent', $userAgent);
            return $req;
        }));
    }
}
