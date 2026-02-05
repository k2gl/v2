<?php

namespace App\Task\Features\CreateTask;

use App\User\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

#[OA\Tag(name: 'Tasks')]
#[Route('/api/tasks', methods: ['POST'])]
final class CreateTaskAction extends AbstractController
{
    public function __invoke(
        #[MapRequestPayload] CreateTaskMessage $message,
        CreateTaskHandler $handler
    ): TaskCreatedResponse {
        $user = $this->getUser();
        assert($user instanceof User);
        return $handler->handle($message, $user);
    }
}
