<?php

declare(strict_types=1);

namespace IPS\perscommigrator\Perscom;

use IPS\Http\_Url;

class _api
{
    protected $apiUrl;
    protected $apiKey;
    protected $perscomId;

    public function __construct(string $apiUrl, string $apiKey, string $perscomId)
    {
        $this->apiUrl = mb_substr($apiUrl, -1) === '/' ? $apiUrl : $apiUrl . '/';
        $this->apiKey = $apiKey;
        $this->perscomId = $perscomId;
    }

    /**
     * @throws \Exception
     */
    public function __call($method, $args)
    {
        $request = $this->prepareRequest($args[0]);

        $response = isset($args[1])
            ? $request->$method(is_array($args[1]) ? json_encode($args[1], JSON_THROW_ON_ERROR) : $args[1])
            : $request->$method();

        usleep(600);

        if ($response->httpResponseCode >= 400) {
            throw new \RuntimeException('Error in API request: ' . $response->content);
        }

        return !empty($response->content)
            ? json_decode($response->content, true, 512, JSON_THROW_ON_ERROR)
            : null;
    }

    protected function prepareRequest(string $endpoint)
    {
        $request = \IPS\Http\Url::external($this->apiUrl . $endpoint)->request();
        $request->setHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-Perscom-Id' => $this->perscomId,
        ]);

        return $request;
    }
}
