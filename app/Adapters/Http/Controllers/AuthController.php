<?php

declare(strict_types=1);

namespace App\Adapters\Http\Controllers;

use App\Adapters\Http\Input;
use App\Application\Services\CreateLibrarianService;
use App\Application\Services\LoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController
{
    public function login(Request $request, LoginService $login): JsonResponse
    {
        $v = Input::validate($request, ['username' => 'required|string', 'password' => 'required|string']);
        $token = $login->execute($v['username'], $v['password']);

        return response()->json([
            'access_token' => $token->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $token->expiresIn,
            'role' => $token->role,
        ]);
    }

    public function createUser(Request $request, CreateLibrarianService $create): JsonResponse
    {
        $v = Input::validate($request, ['username' => 'required|string', 'password' => 'required|string', 'role' => 'required|string']);
        $user = $create->execute($v['username'], $v['password'], $v['role']);

        return response()->json(['id' => $user->id, 'username' => $user->username, 'role' => $user->role], 201);
    }
}
