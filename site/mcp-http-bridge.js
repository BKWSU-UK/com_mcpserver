#!/usr/bin/env node

/*
 * MCP Server for Joomla
 * Copyright (C) 2026 Onepoint Consulting Ltd
 * Licensed under the GNU General Public License version 2 or later.
 */

/**
 * MCP HTTP-to-stdio Bridge
 * 
 * This script bridges Claude Desktop's stdio transport to a remote HTTP MCP server.
 * Usage: node mcp-http-bridge.js <endpoint-url> [bearer-token]
 */

const https = require('https');
const http = require('http');
const { URL } = require('url');

const endpoint = process.argv[2];
const bearerToken = process.argv[3] || process.env.HTTP_AUTH_BEARER || '';
const rejectUnauthorized = process.env.MCP_IGNORE_SSL !== '1';

function log(message, data = null) {
    const timestamp = new Date().toISOString().replace('T', ' ').replace('Z', '');
    const dataStr = data ? ` ${JSON.stringify(data)}` : '';
    process.stderr.write(`${timestamp} [info] ${message}${dataStr}\n`);
}

if (!endpoint) {
    console.error('Error: Endpoint URL is required');
    console.error('Usage: node mcp-http-bridge.js <endpoint-url> [bearer-token]');
    process.exit(1);
}

log(`Connected to stdio server, bridging to ${endpoint}`);

function sendRequest(id, method, params = {}) {
    return new Promise((resolve, reject) => {
        const url = new URL(endpoint);
        const isHttps = url.protocol === 'https:';
        const client = isHttps ? https : http;

        const payload = JSON.stringify({
            jsonrpc: '2.0',
            id: id,
            method,
            params
        });

        const options = {
            hostname: url.hostname,
            port: url.port || (isHttps ? 443 : 80),
            path: url.pathname + url.search,
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Content-Length': Buffer.byteLength(payload)
            },
            rejectUnauthorized: rejectUnauthorized
        };

        if (bearerToken) {
            options.headers['Authorization'] = `Bearer ${bearerToken}`;
        }

        const req = client.request(options, (res) => {
            let data = '';
            res.on('data', (chunk) => data += chunk);
            res.on('end', () => {
                if (res.statusCode === 204) {
                    resolve(null);
                    return;
                }
                if (res.statusCode >= 400 && !data.trim().startsWith('{')) {
                    reject(new Error(`HTTP Error ${res.statusCode}: ${data.slice(0, 100)}`));
                    return;
                }
                try {
                    const response = JSON.parse(data);
                    if (response && response.id === null && id !== undefined && id !== null) {
                        response.id = id;
                    }
                    resolve(response);
                } catch (error) {
                    reject(new Error(`Invalid JSON response (HTTP ${res.statusCode}): ${error.message}`));
                }
            });
        });

        req.on('error', reject);
        req.write(payload);
        req.end();
    });
}

function writeOutput(data) {
    process.stdout.write(JSON.stringify(data) + '\n');
}

const PAGINATED_LIST_METHODS = {
    'tools/list': 'tools',
    'resources/list': 'resources',
    'resources/templates/list': 'resourceTemplates',
    'prompts/list': 'prompts',
};

async function fetchAllListPages(requestId, method, params, itemsKey) {
    const allItems = [];
    let cursor = params?.cursor;
    let lastResponse = null;
    let page = 0;

    while (true) {
        const pageParams = { ...(params || {}) };
        if (cursor) {
            pageParams.cursor = cursor;
        } else {
            delete pageParams.cursor;
        }

        const pageId = page === 0 ? requestId : `${requestId}-page-${page}`;
        const response = await sendRequest(pageId, method, pageParams);

        if (!response || response.error) {
            return response;
        }

        lastResponse = response;
        const items = response.result?.[itemsKey];
        if (Array.isArray(items)) {
            allItems.push(...items);
        }

        cursor = response.result?.nextCursor;
        if (!cursor) {
            break;
        }
        page++;
    }

    if (lastResponse?.result) {
        const result = { ...lastResponse.result, [itemsKey]: allItems };
        delete result.nextCursor;
        return { ...lastResponse, result };
    }

    return lastResponse;
}

let serverName = 'remote-mcp-server';

async function handleInput(line) {
    let requestId = undefined;
    try {
        const request = JSON.parse(line);
        requestId = request.id !== undefined ? request.id : undefined;
        const { method, params } = request;

        log(`Handling ${method} request`, { server: serverName });

        const itemsKey = PAGINATED_LIST_METHODS[method];
        const response = itemsKey && !params?.cursor
            ? await fetchAllListPages(requestId, method, params, itemsKey)
            : await sendRequest(requestId, method, params);
        
        if (response && response.result && response.result.serverInfo) {
            serverName = response.result.serverInfo.name;
        }

        if (method === 'tools/list' && response && response.result && response.result.tools) {
            log(`listTools: Found ${response.result.tools.length} tools`, { server: serverName });
        }

        if (response !== null) {
            writeOutput(response);
        }
    } catch (error) {
        writeOutput({
            jsonrpc: '2.0',
            id: requestId,
            error: {
                code: -32603,
                message: error.message
            }
        });
    }
}

let buffer = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', (chunk) => {
    buffer += chunk;
    const lines = buffer.split('\n');
    buffer = lines.pop();

    for (const line of lines) {
        if (line.trim()) {
            handleInput(line.trim());
        }
    }
});

process.stdin.on('end', () => {
    if (buffer.trim()) {
        handleInput(buffer.trim());
    }
});

process.on('SIGINT', () => process.exit(0));
process.on('SIGTERM', () => process.exit(0));

