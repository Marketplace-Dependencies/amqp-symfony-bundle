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
            'base_uri' => $_ENV['JURRY_BASE_API_URI'],
            'timeout' => $_ENV['JURRY_HTTP_CLIENT_TIMEOUT']
        ]);
    }

    /**
     * @param string $route
     * @param string $method
     * @param array $headers
     * @param array $query
     * @param array $body
     * @return string|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function process(string $route = '', string $method = '', array $headers = [], array $query = [], array $body = [])
    {
        if (!empty($route)) {
            $request = $this->httpClient->request($method, $route, [
                'headers' => $headers,
                'json' => $body,
                'query' => $query
            ]);

            return $request->getBody()->getContents();
        }

        return null;
    }
}
