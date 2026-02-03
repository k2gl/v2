<?php

namespace App\User\Infrastructure\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\Security\Core\User\UserInterface;

final class JWTCreatedListener
{
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $payload = $event->getData();
        $user = $event->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        $payload['user_id'] = $user->getId();
        $payload['email'] = $user->getEmail();
        $payload['roles'] = $user->getRoles();

        if (method_exists($user, 'getBoards')) {
            $payload['mercure'] = [
                'subscribe' => [
                    "https://your-kanban.com/user/{$user->getId()}/boards"
                ]
            ];
        }

        $event->setData($payload);
    }
}
