<?php

namespace App\Task\Features\HealthCheck;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class HealthCheckAction extends AbstractController
{
    #[Route('/healthz', name: 'health_check', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'timestamp' => date('c'),
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
        ]);
    }
}
