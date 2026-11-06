<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Http;

use App\Shared\Infrastructure\Http\JsonBody;
use App\User\Application\Profile\ChangePasswordHandler;
use App\User\Application\Profile\UpdateProfileHandler;
use App\User\Application\Query\GetUserHandler;
use App\User\Infrastructure\Security\SecurityUser;
use App\User\Infrastructure\Security\SecurityUserProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Security;

/**
 * Perfil del usuario autenticado (cualquier rol).
 *
 * @Route("/api/me")
 */
final class ProfileController
{
    private const FIREWALL = 'main';

    private Security $security;
    private TokenStorageInterface $tokens;
    private SecurityUserProvider $userProvider;
    private GetUserHandler $getUser;
    private UpdateProfileHandler $updateProfile;
    private ChangePasswordHandler $changePassword;

    public function __construct(
        Security $security,
        TokenStorageInterface $tokens,
        SecurityUserProvider $userProvider,
        GetUserHandler $getUser,
        UpdateProfileHandler $updateProfile,
        ChangePasswordHandler $changePassword
    ) {
        $this->security = $security;
        $this->tokens = $tokens;
        $this->userProvider = $userProvider;
        $this->getUser = $getUser;
        $this->updateProfile = $updateProfile;
        $this->changePassword = $changePassword;
    }

    /**
     * @Route("", name="api_me", methods={"GET"})
     */
    public function show(): JsonResponse
    {
        return new JsonResponse($this->getUser->handle($this->currentUserId())->toArray());
    }

    /**
     * @Route("", name="api_me_update", methods={"PUT"})
     */
    public function update(Request $request): JsonResponse
    {
        $body = JsonBody::decode($request);

        $view = $this->updateProfile->handle(
            $this->currentUserId(),
            JsonBody::string($body, 'fullName'),
            JsonBody::string($body, 'email')
        );

        return new JsonResponse($view->toArray());
    }

    /**
     * @Route("/password", name="api_me_password", methods={"PUT"})
     */
    public function changePassword(Request $request): Response
    {
        $body = JsonBody::decode($request);
        $userId = $this->currentUserId();

        $this->changePassword->handle(
            $userId,
            JsonBody::string($body, 'currentPassword'),
            JsonBody::string($body, 'newPassword')
        );

        // Cambiar la contraseña invalida las demás sesiones del usuario (su hash ya no coincide).
        // Se renueva el token de ESTA sesión con el hash nuevo para que no se cierre.
        $current = $this->userProvider->loadUserByUsername($this->getUser->handle($userId)->email);
        $this->tokens->setToken(new UsernamePasswordToken($current, null, self::FIREWALL, $current->getRoles()));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function currentUserId(): int
    {
        /** @var SecurityUser $user */
        $user = $this->security->getUser();

        return $user->id();
    }
}
