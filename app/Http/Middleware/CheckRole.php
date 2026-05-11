<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Verificar que el usuario esté autenticado
        if (!$request->user()) {
            return response()->json([
                'message' => 'No autenticado'
            ], 401);
        }

        // Verificar que el usuario tenga uno de los roles permitidos
        if (!in_array($request->user()->role, $roles)) {
            return response()->json([
                'message' => 'No tienes permisos para acceder a este recurso',
                'required_roles' => $roles,
                'your_role' => $request->user()->role
            ], 403);
        }

        return $next($request);
    }
}