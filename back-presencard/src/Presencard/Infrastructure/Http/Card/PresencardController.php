<?php

declare(strict_types=1);

namespace App\Presencard\Infrastructure\Http\Card;

use App\Presencard\Application\Create\CreatePresencardCommand;
use App\Presencard\Application\Create\CreatePresencardHandler;
use App\Presencard\Application\Delete\DeletePresencardHandler;
use App\Presencard\Application\PresencardView;
use App\Presencard\Application\Query\GetPresencardHandler;
use App\Presencard\Application\Query\ListPresencardsHandler;
use App\Presencard\Application\Update\UpdatePresencardCommand;
use App\Presencard\Application\Update\UpdatePresencardHandler;
use App\Presencard\Infrastructure\Http\ActorResolver;
use App\Presencard\Infrastructure\Http\PublicUrlBuilder;
use App\Shared\Infrastructure\Http\JsonBody;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * CRUD de tarjetas del usuario autenticado. El administrador gestiona todas; el usuario
 * normal solo las suyas (la regla vive en los casos de uso, vía Actor).
 *
 * @Route("/api/cards")
 */
final class PresencardController
{
    private CreatePresencardHandler $createHandler;
    private UpdatePresencardHandler $updateHandler;
    private DeletePresencardHandler $deleteHandler;
    private ListPresencardsHandler $listHandler;
    private GetPresencardHandler $getHandler;
    private PublicUrlBuilder $urls;
    private ActorResolver $actors;

    public function __construct(
        CreatePresencardHandler $create,
        UpdatePresencardHandler $update,
        DeletePresencardHandler $delete,
        ListPresencardsHandler $list,
        GetPresencardHandler $get,
        PublicUrlBuilder $urls,
        ActorResolver $actors
    ) {
        $this->createHandler = $create;
        $this->updateHandler = $update;
        $this->deleteHandler = $delete;
        $this->listHandler = $list;
        $this->getHandler = $get;
        $this->urls = $urls;
        $this->actors = $actors;
    }

    /**
     * @Route("", name="cards_list", methods={"GET"})
     */
    public function list(Request $request): JsonResponse
    {
        $page = $this->listHandler->handle(
            $this->actors->current(),
            $request->query->get('q'),
            (int) $request->query->get('page', 1),
            (int) $request->query->get('perPage', ListPresencardsHandler::DEFAULT_PER_PAGE)
        );

        $data = $page->toArray();
        $data['items'] = array_map(function (PresencardView $view): array {
            return $this->present($view);
        }, $page->items);

        return new JsonResponse($data);
    }

    /**
     * @Route("", name="cards_create", methods={"POST"})
     */
    public function create(Request $request): JsonResponse
    {
        $body = JsonBody::decode($request);

        $view = $this->createHandler->handle(new CreatePresencardCommand(
            $this->actors->current()->userId(),
            JsonBody::string($body, 'fullName'),
            JsonBody::nullableString($body, 'jobTitle'),
            JsonBody::nullableString($body, 'company'),
            JsonBody::nullableString($body, 'email'),
            JsonBody::nullableString($body, 'phone'),
            JsonBody::nullableString($body, 'whatsapp'),
            JsonBody::nullableString($body, 'instagram'),
            JsonBody::nullableString($body, 'telegram'),
            JsonBody::nullableString($body, 'colorPrimary'),
            JsonBody::nullableString($body, 'colorAccent')
        ));

        return new JsonResponse($this->present($view), Response::HTTP_CREATED);
    }

    /**
     * @Route("/{id}", name="cards_get", methods={"GET"}, requirements={"id"="\d+"})
     */
    public function get(int $id): JsonResponse
    {
        return new JsonResponse($this->present($this->getHandler->handle($this->actors->current(), $id)));
    }

    /**
     * @Route("/{id}", name="cards_update", methods={"PUT"}, requirements={"id"="\d+"})
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $body = JsonBody::decode($request);

        $view = $this->updateHandler->handle(new UpdatePresencardCommand(
            $this->actors->current(),
            $id,
            JsonBody::string($body, 'fullName'),
            JsonBody::nullableString($body, 'jobTitle'),
            JsonBody::nullableString($body, 'company'),
            JsonBody::nullableString($body, 'email'),
            JsonBody::nullableString($body, 'phone'),
            JsonBody::nullableString($body, 'whatsapp'),
            JsonBody::nullableString($body, 'instagram'),
            JsonBody::nullableString($body, 'telegram'),
            JsonBody::nullableString($body, 'colorPrimary'),
            JsonBody::nullableString($body, 'colorAccent')
        ));

        return new JsonResponse($this->present($view));
    }

    /**
     * @Route("/{id}", name="cards_delete", methods={"DELETE"}, requirements={"id"="\d+"})
     */
    public function delete(int $id): Response
    {
        $this->deleteHandler->handle($this->actors->current(), $id);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array<string, mixed> */
    private function present(PresencardView $view): array
    {
        return $view->toArray() + ['publicUrl' => $this->urls->forHashId($view->hashId)];
    }
}
