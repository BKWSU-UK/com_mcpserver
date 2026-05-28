<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;

class RestClient
{
    private GuzzleClient $http;
    private string $baseUrl;
    private ?string $apiToken;
    private LoggerInterface $logger;

    public function __construct(string $baseUrl, ?string $apiToken, LoggerInterface $logger, bool $verifySsl = true)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $apiToken = $apiToken !== null ? trim($apiToken) : null;
        $this->apiToken = $apiToken !== '' ? $apiToken : null;
        $this->logger = $logger;
        $this->http = new GuzzleClient([
            'base_uri' => $this->baseUrl . '/',
            'timeout' => 15.0,
            'verify' => $verifySsl,
        ]);
        
        $this->logger->info('RestClient initialized', [
            'base_url' => $this->baseUrl,
            'has_token' => !empty($this->apiToken),
        ]);
    }

    public function get(string $path, array $query = []): array
    {
        $headers = $this->authHeaders();
        try {
			$response = $this->http->request('GET', ltrim($path, '/'), [
                'headers' => $headers,
                'query' => $query,
            ]);
            $body = (string) $response->getBody();
            $this->logger->debug('REST GET response', ['status' => $response->getStatusCode(), 'body' => substr($body, 0, 500)]);
            return json_decode($body, true) ?? [];
        } catch (GuzzleException $e) {
            $this->logger->error('REST GET failed', [
                'exception' => $e->getMessage(),
                'path' => $path,
                'query' => $query,
                'code' => $e->getCode(),
            ]);
            throw $e;
        }
    }

	public function post(string $path, array $jsonBody = []): array
	{
		$headers = $this->authHeaders();
		try {
			$response = $this->http->request('POST', ltrim($path, '/'), [
				'headers' => $headers,
				RequestOptions::JSON => $jsonBody,
			]);
			$body = (string) $response->getBody();
			$this->logger->debug('REST POST response', ['status' => $response->getStatusCode(), 'body' => substr($body, 0, 500)]);
			return json_decode($body, true) ?? [];
		} catch (GuzzleException $e) {
			$this->logger->error('REST POST failed', [
				'exception' => $e->getMessage(),
				'path' => $path,
				'code' => $e->getCode(),
			]);
			throw $e;
		}
	}

	public function patch(string $path, array $jsonBody = []): array
	{
		$headers = $this->authHeaders();
		try {
			$response = $this->http->request('PATCH', ltrim($path, '/'), [
				'headers' => $headers,
				RequestOptions::JSON => $jsonBody,
			]);
			$body = (string) $response->getBody();
			$this->logger->debug('REST PATCH response', ['status' => $response->getStatusCode(), 'body' => $body]);
			return json_decode($body, true) ?? [];
		} catch (GuzzleException $e) {
			$this->logger->error('REST PATCH failed', [
				'exception' => $e->getMessage(),
				'path' => $path,
				'code' => $e->getCode(),
			]);
			throw $e;
		}
	}

	public function delete(string $path): array
	{
		$headers = $this->authHeaders();
		try {
			$response = $this->http->request('DELETE', ltrim($path, '/'), [
				'headers' => $headers,
			]);
			$body = (string) $response->getBody();
			$this->logger->debug('REST DELETE response', ['status' => $response->getStatusCode(), 'body' => $body]);
			return json_decode($body, true) ?? [];
		} catch (GuzzleException $e) {
			$this->logger->error('REST DELETE failed', [
				'exception' => $e->getMessage(),
				'path' => $path,
				'code' => $e->getCode(),
			]);
			throw $e;
		}
	}

	public function fetchUrlContent(string $url): string
	{
		try {
			$client = new GuzzleClient([
				'timeout' => 30.0,
				'verify' => $this->http->getConfig('verify'),
			]);
			$response = $client->request('GET', $url);
			$this->logger->debug('Fetched URL content', [
				'url' => $url,
				'status' => $response->getStatusCode(),
				'bytes' => (int) $response->getBody()->getSize(),
			]);
			return (string) $response->getBody();
		} catch (GuzzleException $e) {
			$this->logger->error('Fetch URL failed', [
				'url' => $url,
				'exception' => $e->getMessage(),
				'code' => $e->getCode(),
			]);
			throw $e;
		}
	}

    private function authHeaders(): array
    {
        $headers = [
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
        ];
        if (!empty($this->apiToken)) {
            $headers['Authorization'] = 'Bearer ' . $this->apiToken;
            $headers['X-Joomla-Token'] = $this->apiToken;
        }
        
        return $headers;
    }
}


