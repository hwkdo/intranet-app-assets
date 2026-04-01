<?php

namespace Hwkdo\IntranetAppAssets\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogMcpAssetsHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = (string) $request->header('authorization', '');
        $token = preg_replace('/^Bearer\s+/i', '', $authorization) ?? '';

        Log::info('MCP assets request headers', [
            'path' => $request->path(),
            'method' => $request->method(),
            'has_authorization_header' => $authorization !== '',
            'authorization_prefix' => str_starts_with($authorization, 'Bearer ') ? 'Bearer' : 'other_or_missing',
            'authorization_preview' => $authorization !== ''
                ? mb_substr($authorization, 0, 24).'...'
                : null,
            'token_length' => $token !== '' ? mb_strlen($token) : 0,
            'token_dot_count' => $token !== '' ? substr_count($token, '.') : 0,
            'all_header_keys' => array_keys($request->headers->all()),
        ]);

        return $next($request);
    }
}
