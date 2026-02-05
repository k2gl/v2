<?php

namespace App\Board\Features\CreateBoard;

use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Boards")]
final class CreateBoardAction extends AbstractController
{
    #[Route('/api/boards', name: 'create_board', methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: "Board created successfully",
        content: new OA\JsonContent(ref: "#/components/schemas/BoardCreatedResponse")
    )]
    #[OA\Response(response: 401, description: "Unauthorized")]
    public function __invoke(
        #[MapRequestPayload] CreateBoardMessage $message,
        CreateBoardHandler $handler
    ): BoardCreatedResponse {
        $user = $this->getUser();
        assert($user instanceof User);
        return $handler->handle($message, $user);
    }
}
