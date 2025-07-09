<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Login simple para hackathon
    public function login(Request $request)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'email'    => 'required|string|email|max:255',
            'password' => 'required|string|min:6',
        ]);
    
        // Si la validación falla, devolver un error
        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }
    
        // Buscar usuario por email
        $user = User::where('email', $request->email)->first();
    
        // Verificar si el usuario existe y la contraseña es correcta
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }
    
        // Respuesta exitosa con datos del usuario
        return response()->json([
            'status'  => 'success',
            'message' => 'Login successful',
            'data'    => [
                'user' => $user,
            ],
        ], 200);
    }

    // Obtener todos los usuarios (para demo)
    public function getAllUsers()
    {
        $users = User::all();
        
        return response()->json([
            'status'  => 'success',
            'message' => 'Users retrieved successfully',
            'data'    => $users,
        ], 200);
    }

    // Obtener usuario por ID (para demo)
    public function getUser(Request $request)
    {
        // Si no se proporciona ID, devolver error
        $userId = $request->query('id');
        
        if (!$userId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User ID is required',
            ], 400);
        }

        $user = User::find($userId);
        
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found',
            ], 404);
        }
        
        return response()->json([
            'status'  => 'success',
            'message' => 'User retrieved successfully',
            'data'    => $user,
        ], 200);
    }

    // Logout simple (solo mensaje de confirmación)
    public function logout()
    {
        return response()->json([
            'status'  => 'success',
            'message' => 'Logout successful',
        ], 200);
    }
}
