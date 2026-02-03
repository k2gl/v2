<?php

namespace App\Task\Features\CreateTask;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[OA\Tag(name: 'Tasks')]
#[Route('/api/tasks', methods: ['POST'])]
final class CreateTaskAction extends AbstractController
{
    public function __invoke(
        #[MapRequestPayload] CreateTaskMessage $message,
        CreateTaskHandler $handler,
        #[CurrentUser] $user
    ): TaskCreatedResponse {
        return $handler->handle($message, $user);
    }
}
