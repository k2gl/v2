<?php

namespace App\Tests\Task\Features\ReorderTasks;

use App\Task\Entity\Task;
use App\Task\Entity\TaskRepository;
use App\Task\Features\ReorderTasks\ReorderTasksMessage;
use App\Task\Features\ReorderTasks\ReorderTasksHandler;
use DAMA\DoctrineTestBundle\DoctrineTestBundle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ReorderTasksTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testTasksAreReordered(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/tasks/reorder', content: json_encode([
            'columnId' => 1,
            'orderedIds' => [3, 1, 2]
        ]));

        $this->assertResponseIsSuccessful();

        $container = self::getContainer();
        $repository = $container->get(TaskRepository::class);

        $task1 = $repository->find(1);
        $task2 = $repository->find(2);
        $task3 = $repository->find(3);

        $this->assertEquals(1, $task1->getPosition());
        $this->assertEquals(2, $task2->getPosition());
        $this->assertEquals(0, $task3->getPosition());
    }

    public function testInvalidReorderRequestFails(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/tasks/reorder', content: json_encode([
            'columnId' => 'invalid',
            'orderedIds' => []
        ]));

        $this->assertResponseStatusCodeSame(400);
    }
}
