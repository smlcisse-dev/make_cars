<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Auth\RegisterAutomobilisteRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function automobiliste(RegisterAutomobilisteRequest $request): JsonResponse
    {
        $user = $this->authService->registerAutomobiliste($request->validated());
        $token = $this->authService->issueToken($user);

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
        ], 'Compte automobiliste créé.', 201);
    }
}
