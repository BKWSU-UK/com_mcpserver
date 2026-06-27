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
    private ?string $resolveIp;
    private LoggerInterface $logger;

    public function __construct(string $baseUrl, ?string $apiToken, LoggerInterface $logger, bool $verifySsl = true, ?string $resolveIp = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $apiToken = $apiToken !== null ? trim($apiToken) : null;
        $this->apiToken = $apiToken !== '' ? $apiToken : null;
        $this->logger = $logger;
        $this->resolveIp = $resolveIp !== null && trim($resolveIp) !== '' ? trim($resolveIp) : null;

        $config = [
            'base_uri' => $this->baseUrl . '/',
            'timeout' => 15.0,
            'verify' => $verifySsl,
        ];

        // Pin the base host to a specific IP (e.g. 127.0.0.1) for this client only,
        // so the box can reach its own public hostname without NAT hairpinning while
        // still sending the correct Host header and validating TLS against the real name.
        $resolveEntry = $this->resolveIp !== null ? $this->buildResolveEntry($this->resolveIp) : null;
        if ($resolveEntry !== null) {
            $config['curl'] = [\CURLOPT_RESOLVE => [$resolveEntry]];
        }

        $this->http = new GuzzleClient($config);

        $this->logger->info('RestClient initialized', [
            'base_url' => $this->baseUrl,
            'has_token' => !empty($this->apiToken),
            'resolve' => $resolveEntry,
        ]);
    }

    /**
     * Build a CURLOPT_RESOLVE entry ("host:port:ip") for a URL host and override IP.
     * For non-base URLs, resolution applies only when the hostname matches the base URL.
     */
    private function buildResolveEntry(string $resolveIp, ?string $targetUrl = null): ?string
    {
        $targetUrl = $targetUrl ?? $this->baseUrl;
        $host = parse_url($targetUrl, \PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }

        if ($targetUrl !== $this->baseUrl) {
            $baseHost = parse_url($this->baseUrl, \PHP_URL_HOST);
            if (!is_string($baseHost) || strcasecmp($host, $baseHost) !== 0) {
                return null;
            }
        }

        $scheme = strtolower((string) parse_url($targetUrl, \PHP_URL_SCHEME));
        $port = parse_url($targetUrl, \PHP_URL_PORT);
        if (!is_int($port)) {
            $port = $scheme === 'https' ? 443 : 80;
        }

        // Tolerate a value pasted as a full "host:port:ip" CURLOPT_RESOLVE entry —
        // take the last colon-separated token as the IP. IPv6 must be bracketed.
        $ip = $resolveIp;
        if (strpos($ip, ':') !== false && strpos($ip, '[') === false) {
            $parts = explode(':', $ip);
            $ip = (string) end($parts);
        }
        $ip = trim($ip, "[]");

        if (filter_var($ip, \FILTER_VALIDATE_IP) === false) {
            $this->logger->warning('Ignoring invalid resolve_ip; expected a bare IP address', [
                'resolve_ip' => $resolveIp,
            ]);
            return null;
        }

        return $host . ':' . $port . ':' . $ip;
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

	public function fetchUrlContent(string $url, float $timeout = 30.0): string
	{
		try {
			$config = [
				'timeout' => $timeout,
				'verify' => $this->http->getConfig('verify'),
			];

			$resolveEntry = $this->resolveIp !== null ? $this->buildResolveEntry($this->resolveIp, $url) : null;
			if ($resolveEntry !== null) {
				$config['curl'] = [\CURLOPT_RESOLVE => [$resolveEntry]];
			}

			$client = new GuzzleClient($config);
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


