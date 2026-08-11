<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        // Forzar respuesta JSON para que las excepciones de validación retornen 422 en vez de redirigir
        $request->headers->set('Accept', 'application/json');

        $body = $request->all();
        foreach (['password', 'password_confirmation', 'token', 'secret'] as $key) {
            if (isset($body[$key])) {
                $body[$key] = '***';
            }
        }

        Log::channel('api')->info('→ ' . $request->method() . ' ' . $request->path(), [
            'body' => $body ?: null,
            'ip'   => $request->ip(),
        ]);

        $response = $next($request);

        $raw     = $response->getContent();
        $decoded = json_decode($raw, true);
        $preview = (json_last_error() === JSON_ERROR_NONE)
            ? $decoded
            : (strlen($raw) > 300 ? substr($raw, 0, 300) . '…' : $raw);

        Log::channel('api')->info('← ' . $response->getStatusCode(), [
            'body' => $preview,
        ]);

        return $response;
    }
}
