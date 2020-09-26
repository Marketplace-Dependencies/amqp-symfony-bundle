<?php
/**
 * User: Wajdi Jurry
 * Date: 24/05/20
 * Time: 04:40 م
 */

namespace Jurry\RabbitMQ\Handler;


use GuzzleHttp\Client;

class RequestHandler
{
    private $httpClient;

    public function __construct()
    {
        $this->httpClient = new Client([
            'base_uri' => env('JURRY_BASE_API_URI'),
            'timeout' => env('JURRY_HTTP_CLIENT_TIMEOUT')
        ]);
    }

    /**
     * @param string $route
     * @param string $method
     * @param array $body
     * @param array $query
     * @return string|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function process(string $route = '', string $method = '', array $query = [], array $body = [])
    {
        if (!empty($route)) {
            $request = $this->httpClient->request($method, $route, [
                'json' => $body,
                'query' => $query
            ]);

            return $request->getBody()->getContents();
        }

        return null;
    }
}
