<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Brauzer darajasidagi himoya sarlavhalari: CSP (nonce bilan), clickjacking, MIME sniffing,
 * hamda shaxsiy ma'lumotli sahifalarni keshlamaslik.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        /** @var Response $response */
        $response = $next($request);

        $viteDevServer = Vite::isRunningHot() ? ' '.rtrim((string) file_get_contents(public_path('hot'))) : '';
        $viteSocket = $viteDevServer ? ' '.str_replace(['http://', 'https://'], ['ws://', 'wss://'], trim($viteDevServer)) : '';

        $policy = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'{$viteDevServer}",
            // ApexCharts va Blade'dagi style="" atributlari uchun
            "style-src 'self' 'unsafe-inline'{$viteDevServer}",
            "img-src 'self' data: https://hemis.jdpu.uz https://student.jdpu.uz",
            "font-src 'self' data:{$viteDevServer}",
            "connect-src 'self'{$viteDevServer}{$viteSocket}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);

        $headers = [
            'Content-Security-Policy' => $policy,
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        if ($request->user()) {
            // Talabalar ma'lumoti brauzer yoki proksi keshida qolmasin
            $headers['Cache-Control'] = 'no-store, private';
            $headers['Pragma'] = 'no-cache';
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }
        $response->headers->remove('X-Powered-By');
        header_remove('X-Powered-By');

        return $response;
    }
}
