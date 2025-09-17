<?php declare(strict_types=1);

namespace Sprii\LiveShoppingIntegration\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractApiService
{
    protected Client $client;
    protected Logger $logger;
    protected string $apiBaseUri = 'https://app.sprii.io/functions/shopware';

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;

        $this->client = new Client([
            'base_uri' => $this->apiBaseUri,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $params
     * @return ResponseInterface
     * @throws GuzzleException
     */
    protected function callApi(
        string $method,
        string $url,
        array $data = [],
        array $params = []
    ): ResponseInterface {
        $requestUrl = $this->apiBaseUri . $url;
        $headers = [];

        if ($data) {
            $headers['json'] = $data;
        }

        if ($params) {
            $headers['query'] = $params;
        }

        return $this->client->request($method, $requestUrl, $headers);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $params
     * @return PromiseInterface
     */
    protected function callApiAsync(
        string $method,
        string $url,
        array $data = [],
        array $params = []
    ): PromiseInterface {
        $requestUrl = $this->apiBaseUri . $url;
        $headers = [];

        if ($data) {
            $headers['json'] = $data;
        }

        if ($params) {
            $headers['query'] = $params;
        }

        return $this->client->requestAsync($method, $requestUrl, $headers);
    }
}
