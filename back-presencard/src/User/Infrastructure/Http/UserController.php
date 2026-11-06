<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Http;

use App\Shared\Infrastructure\Http\JsonBody;
use App\User\Application\Create\CreateUserCommand;
use App\User\Application\Create\CreateUserHandler;
use App\User\Application\Delete\DeleteUserHandler;
use App\User\Application\Query\GetUserHandler;
use App\User\Application\Query\ListUsersHandler;
use App\User\Application\ResetPassword\ResetUserPasswordHandler;
use App\User\Application\SetActive\SetUserActiveHandler;
use App\User\Application\Update\UpdateUserCommand;
use App\User\Application\Update\UpdateUserHandler;
use App\User\Domain\Exception\InvalidUser;
use App\User\Domain\UserRole;
use App\User\Infrastructure\Security\SecurityUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

/**
 * Gestión de usuarios: solo administradores (access_control en security.yaml).
 *
 * @Route("/api/users")
 */
final class UserController
{
    private Security $security;
    private CreateUserHandler $createUser;
    private UpdateUserHandler $updateUser;
    private ResetUserPasswordHandler $resetPassword;
    private SetUserActiveHandler $setActive;
    private DeleteUserHandler $deleteUser;
    private ListUsersHandler $listUsers;
    private GetUserHandler $getUser;

    public function __construct(
        Security $security,
        CreateUserHandler $createUser,
        UpdateUserHandler $updateUser,
        ResetUserPasswordHandler $resetPassword,
        SetUserActiveHandler $setActive,
        DeleteUserHandler $deleteUser,
        ListUsersHandler $listUsers,
        GetUserHandler $getUser
    ) {
        $this->security = $security;
        $this->createUser = $createUser;
        $this->updateUser = $updateUser;
        $this->resetPassword = $resetPassword;
        $this->setActive = $setActive;
        $this->deleteUser = $deleteUser;
        $this->listUsers = $listUsers;
        $this->getUser = $getUser;
    }

    /**
     * @Route("", name="users_list", methods={"GET"})
     */
    public function list(Request $request): JsonResponse
    {
        $page = $this->listUsers->handle(
            $request->query->get('q'),
            (int) $request->query->get('page', 1),
            (int) $request->query->get('perPage', ListUsersHandler::DEFAULT_PER_PAGE)
        );

        return new JsonResponse($page->toArray());
    }

    /**
     * @Route("", name="users_create", methods={"POST"})
     */
    public function create(Request $request): JsonResponse
    {
        $body = JsonBody::decode($request);

        $view = $this->createUser->handle(new CreateUserCommand(
            JsonBody::string($body, 'email'),
            JsonBody::string($body, 'fullName'),
            JsonBody::string($body, 'password'),
            JsonBody::string($body, 'role') ?: UserRole::USER
        ));

        return new JsonResponse($view->toArray(), Response::HTTP_CREATED);
    }

    /**
     * @Route("/{id}", name="users_get", methods={"GET"}, requirements={"id"="\d+"})
     */
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->getUser->handle($id)->toArray());
    }

    /**
     * @Route("/{id}", name="users_update", methods={"PUT"}, requirements={"id"="\d+"})
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $body = JsonBody::decode($request);

        $view = $this->updateUser->handle(new UpdateUserCommand(
            $this->actorId(),
            $id,
            JsonBody::string($body, 'fullName'),
            JsonBody::string($body, 'email'),
            JsonBody::string($body, 'role')
        ));

        return new JsonResponse($view->toArray());
    }

    /**
     * @Route("/{id}/password", name="users_reset_password", methods={"PUT"}, requirements={"id"="\d+"})
     */
    public function resetPassword(int $id, Request $request): Response
    {
        $body = JsonBody::decode($request);

        $this->resetPassword->handle($id, JsonBody::string($body, 'password'));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/{id}/active", name="users_set_active", methods={"PUT"}, requirements={"id"="\d+"})
     */
    public function setActive(int $id, Request $request): JsonResponse
    {
        $active = JsonBody::bool(JsonBody::decode($request), 'active');

        if ($active === null) {
            throw InvalidUser::because('El campo "active" debe ser true o false.');
        }

        return new JsonResponse($this->setActive->handle($this->actorId(), $id, $active)->toArray());
    }

    /**
     * @Route("/{id}", name="users_delete", methods={"DELETE"}, requirements={"id"="\d+"})
     */
    public function delete(int $id): Response
    {
        $this->deleteUser->handle($this->actorId(), $id);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function actorId(): int
    {
        /** @var SecurityUser $user */
        $user = $this->security->getUser();

        return $user->id();
    }
}
