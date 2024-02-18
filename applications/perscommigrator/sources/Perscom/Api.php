<?php

declare(strict_types=1);

namespace IPS\perscommigrator\Perscom;

use IPS\Http\_Url;

class _api
{
    protected $apiUrl;
    protected $apiKey;
    protected $perscomId;

    public function __construct()
    {
        $apiUrl = \IPS\Settings::i()->perscommigrator_api_url;
        $this->apiUrl = mb_substr($apiUrl, -1) === '/' ? $apiUrl : $apiUrl . '/';
        $this->apiKey = \IPS\Settings::i()->perscommigrator_api_key;
        $this->perscomId = \IPS\Settings::i()->perscommigrator_perscom_id;
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

        return $this->handleResponse($response);
    }

    /**
     * @throws \Exception
     */
    public function uploadImage(string $resource, $file)
    {
        $path = $this->getPathFromFile($file);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $newFilename = uniqid('', false) . '.' . $ext;

        $request = $this->prepareRequest($resource, [
            'Content-Type' => 'multipart/form-data',
        ]);

        $response = $request->post([
            'name' => $newFilename,
            'image' => new \CURLFile($path, '', $newFilename),
        ]);

        $this->handleResponse($response);
    }

    /**
     * @throws \Exception
     */
    public function uploadCoverPhoto($userId, $file)
    {
        $path = $this->getPathFromFile($file);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $newFilename = uniqid('', false) . '.' . $ext;

        $request = $this->prepareRequest('users/' . $userId, [
            'Content-Type' => 'multipart/form-data',
        ]);

        $response = $request->post([
            '_method' => 'put',
            'cover_photo' => new \CURLFile($path, '', $newFilename),
        ]);

        $this->handleResponse($response);
    }

    /**
     * @param \IPS\File $file
     */
    protected function getPathFromFile($file)
    {
        $path = $file->configuration['dir'] . DIRECTORY_SEPARATOR . $file->container . DIRECTORY_SEPARATOR . $file->filename;
        if (!file_exists($path)) {
            throw new \IPS\perscommigrator\Exception\FileNotExistsException('File ' . $path . ' does not exist!');
        }

        return $path;
    }

    protected function prepareRequest(string $endpoint, array $headers = [])
    {
        $request = \IPS\Http\Url::external($this->apiUrl . $endpoint)->request(600);
        $request->setHeaders($this->headers($headers));

        return $request;
    }

    protected function handleResponse($response)
    {
        $limit = 0;
        if (!empty($response->httpHeaders['X-RateLimit-Remaining'])) {
            $limit = $response->httpHeaders['X-RateLimit-Remaining'];
        }

        if ($limit < 100) {
            // slow down there cowboy
            sleep(1);
        }

        if ($response->httpResponseCode >= 400) {
            throw new \RuntimeException('Error in API request: ' . $response->content);
        }

        return !empty($response->content)
            ? json_decode($response->content, true, 512, JSON_THROW_ON_ERROR)
            : null;
    }

    protected function headers(array $overwrite)
    {
        return array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-Perscom-Id' => $this->perscomId,
            'X-Perscom-Notifications' => 'false',
            'X-Perscom-Bypass-Cache' => 'true'
        ], $overwrite);
    }
}
