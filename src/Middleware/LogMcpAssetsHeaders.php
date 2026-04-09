<?php

namespace Hwkdo\IntranetAppAssets\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogMcpAssetsHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = bin2hex(random_bytes(6));
        $startedAt = microtime(true);
        $authorization = (string) $request->header('authorization', '');
        $token = preg_replace('/^Bearer\s+/i', '', $authorization) ?? '';
        $rawBody = (string) $request->getContent();
        $jsonDecoded = null;
        if ($rawBody !== '') {
            $jsonDecoded = json_decode($rawBody, true);
        }

        Log::warning('MCP assets request received', [
            'trace_id' => $traceId,
            'path' => $request->path(),
            'full_url' => $request->fullUrl(),
            'method' => $request->method(),
            'host' => $request->getHost(),
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'has_authorization_header' => $authorization !== '',
            'authorization_prefix' => str_starts_with($authorization, 'Bearer ') ? 'Bearer' : 'other_or_missing',
            'authorization_preview' => $authorization !== ''
                ? mb_substr($authorization, 0, 24).'...'
                : null,
            'token_length' => $token !== '' ? mb_strlen($token) : 0,
            'token_dot_count' => $token !== '' ? substr_count($token, '.') : 0,
            'query_keys' => array_keys($request->query()),
            'request_input_keys' => array_keys($request->all()),
            'content_type' => (string) $request->header('content-type', ''),
            'content_length' => (string) $request->header('content-length', ''),
            'raw_body_bytes' => strlen($rawBody),
            'raw_body_preview' => $rawBody !== '' ? mb_substr($rawBody, 0, 500) : null,
            'json_is_valid' => is_array($jsonDecoded),
            'json_top_level_keys' => is_array($jsonDecoded) ? array_keys($jsonDecoded) : null,
            'all_header_keys' => array_keys($request->headers->all()),
        ]);

        try {
            $response = $next($request);
            Log::warning('MCP assets request completed', [
                'trace_id' => $traceId,
                'status' => $response->getStatusCode(),
                'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);

            return $response;
        } catch (Throwable $e) {
            Log::error('MCP assets request failed before response', [
                'trace_id' => $traceId,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);
            throw $e;
        }
    }
}
