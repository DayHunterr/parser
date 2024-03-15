<?php

namespace App\Util;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

trait RequestAttempt
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @param string $url
     * @param array $params
     *
     * @return ResponseInterface
     *
     * @throws \Exception
     */
    private function sendGETRequest(string $url, array $params = []): ResponseInterface
    {
        $attempt = 1;

        while($attempt <> 5) {
            try {
                return $this->client->get($url, $params);
            } catch (\Throwable $ex) {
                ++$attempt;
            }
        }

        throw new \Exception('Exceeded count of attempts');
    }
}