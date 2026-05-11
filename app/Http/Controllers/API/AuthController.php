<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'nullable|in:teacher,admin',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'teacher',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Usuario registrado exitosamente'
        ], 201);
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Verificar si el usuario existe
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        // Verificar si la cuenta está bloqueada
        if ($user->locked_until && now()->lessThan($user->locked_until)) {
            $minutes = now()->diffInMinutes($user->locked_until);
            throw ValidationException::withMessages([
                'email' => ["Cuenta bloqueada. Intenta de nuevo en {$minutes} minutos."],
            ]);
        }

        // Verificar password
        if (!Hash::check($validated['password'], $user->password)) {
            // Incrementar intentos fallidos
            $user->increment('failed_login_attempts');

            // Bloquear después de 5 intentos
            if ($user->failed_login_attempts >= 5) {
                $user->update(['locked_until' => now()->addMinutes(15)]);
                throw ValidationException::withMessages([
                    'email' => ['Demasiados intentos fallidos. Cuenta bloqueada por 15 minutos.'],
                ]);
            }

            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        // Login exitoso - resetear intentos fallidos
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ]);

        // Crear token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Login exitoso'
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        // Eliminar token actual
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout exitoso'
        ]);
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }
}