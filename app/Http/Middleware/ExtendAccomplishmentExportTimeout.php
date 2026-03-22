<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Excel export can exceed default PHP / proxy budgets on small instances (e.g. DigitalOcean App Platform).
 */
class ExtendAccomplishmentExportTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        @ini_set('max_execution_time', '120');

        return $next($request);
    }
}
