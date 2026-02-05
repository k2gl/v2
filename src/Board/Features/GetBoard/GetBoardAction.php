<?php

namespace App\Board\Features\GetBoard;

use App\Board\Entity\Board;
use App\Board\Repository\BoardRepository;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Boards")]
final class GetBoardAction extends AbstractController
{
    #[Route('/api/boards/{id}', name: 'get_board', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: "Full board structure with columns and tasks",
        content: new OA\JsonContent(ref: "#/components/schemas/BoardResponse")
    )]
    #[OA\Response(response: 401, description: "Unauthorized")]
    #[OA\Response(response: 404, description: "Board not found")]
    public function __invoke(
        int $id,
        BoardRepository $repository
    ): BoardResponse {
        $user = $this->getUser();
        assert($user instanceof User);
        $board = $repository->findFullBoard($id, $user);

        if (!$board) {
            throw new NotFoundHttpException('Board not found or access denied');
        }

        return $this->mapToResponse($board);
    }

    private function mapToResponse(Board $board): BoardResponse
    {
        $columns = array_map(fn($column) => new ColumnDTO(
            $column->getId(),
            $column->getName(),
            (float) $column->getPosition(),
            array_map(fn($task) => new TaskDTO(
                $task->getId(),
                $task->getUuid(),
                $task->getTitle(),
                (float) $task->getPosition(),
                $task->getStatus()->value,
                $task->getDescription(),
                $task->getAssignee()?->getId(),
                $task->getDueDate()?->format('c'),
                $task->getMetadata(),
                $task->getCreatedAt()->format('c')
            ), $column->getTasks()->toArray()),
            $column->getTasks()->count()
        ), $board->getColumns()->toArray());

        return new BoardResponse(
            $board->getId(),
            $board->getTitle(),
            $board->getUuid(),
            $columns,
            $board->getSettings()
        );
    }
}
