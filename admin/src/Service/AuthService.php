<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

class AuthService
{
    private Registry $config;

    public function __construct(Registry $config)
    {
        $this->config = $config;
    }

    public function authenticate(): ?array
    {
        $app = Factory::getApplication();
        $input = $app->input;

        $mcpToken = $this->config->get('mcp_bearer_token', '');
        $ipAllowList = array_filter(array_map('trim', explode(',', $this->config->get('ip_allow_list', ''))));
        $requireAuth = (bool) $this->config->get('require_auth', false);

        if (!empty($ipAllowList)) {
            $remoteIp = $this->getClientIp();
            if (!in_array($remoteIp, $ipAllowList, true)) {
                return ['error' => 'IP not allowed', 'code' => JsonRpc::FORBIDDEN];
            }
        }

        if ($requireAuth) {
            if (empty($mcpToken)) {
                return ['error' => 'Authentication required but no server token configured', 'code' => JsonRpc::FORBIDDEN];
            }

            $authHeader = $input->server->getString('HTTP_AUTHORIZATION', '');
            if (empty($authHeader)) {
                $authHeader = $input->server->getString('REDIRECT_HTTP_AUTHORIZATION', '');
            }

            $providedToken = '';
            if (str_starts_with($authHeader, 'Bearer ')) {
                $providedToken = substr($authHeader, 7);
            }

            if (empty($providedToken)) {
                return ['error' => 'Missing bearer token in Authorization header', 'code' => JsonRpc::UNAUTHORIZED];
            }

            if (!hash_equals($mcpToken, $providedToken)) {
                return ['error' => 'Invalid token', 'code' => JsonRpc::UNAUTHORIZED];
            }
        }

        return null;
    }

    private function getClientIp(): string
    {
        $app = Factory::getApplication();
        $input = $app->input;

        $remoteAddr = $input->server->getString('REMOTE_ADDR', '');

        // Only trust X-Forwarded-For when the direct connection is from a configured trusted proxy
        $trustedProxies = array_filter(array_map('trim', explode(',', (string) $this->config->get('trusted_proxies', ''))));

        if (!empty($trustedProxies) && in_array($remoteAddr, $trustedProxies, true)) {
            $forwarded = $input->server->getString('HTTP_X_FORWARDED_FOR', '');
            if (!empty($forwarded)) {
                $ips = array_map('trim', explode(',', $forwarded));
                return $ips[0];
            }
        }

        return $remoteAddr;
    }
}

