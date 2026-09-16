<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Auth\RegisterAutomobilisteRequest;
use App\Http\Requests\Auth\RegisterProfessionalRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\ProfessionalRegistrationService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ProfessionalRegistrationService $registrationService,
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

    public function professional(RegisterProfessionalRequest $request): JsonResponse
    {
        $user = $this->registrationService->register(
            $request->safe()->except(['business_registration_document', 'premises_photos']),
            $request->file('business_registration_document'),
            $request->file('premises_photos'),
        );

        $token = $this->authService->issueToken($user);

        return $this->success([
            'user' => new UserResource($user->load('professionalRegistration')),
            'token' => $token->plainTextToken,
        ], 'Dossier d\'inscription soumis, en attente de validation par un administrateur.', 201);
    }
}
