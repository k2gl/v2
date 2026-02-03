<?php

namespace App\Task\Features\ReorderTasks;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Tasks")]
final class ReorderTasksAction extends AbstractController
{
    #[Route('/api/tasks/reorder', name: 'reorder_tasks', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload] ReorderTasksMessage $message,
        ReorderTasksHandler $handler
    ): void {
        $handler->handle($message);
    }
}
