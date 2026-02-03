<?php

namespace App\Task\Tests\Features;

use App\Board\Entity\Board;
use App\Board\Entity\Column;
use App\Task\Entity\Task;
use App\Task\Entity\TaskRepository;
use App\Task\Features\CreateTask\CreateTaskMessage;
use App\Task\Features\CreateTask\CreateTaskHandler;
use App\Task\Features\ReorderTasks\ReorderTasksHandler;
use App\Task\Features\ReorderTasks\ReorderTasksMessage;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class TaskLifecycleTest extends WebTestCase
{
    use InteractsWithMessenger;

    private ?EntityManagerInterface $em = null;
    private ?User $user = null;
    private ?Board $board = null;
    private ?Column $column = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        
        $this->user = new User();
        $this->user->setEmail('test_' . uniqid() . '@example.com');
        $this->user->setPassword('hashed_password');
        $this->user->setGithubId('github_' . uniqid());
        $this->em->persist($this->user);

        $this->board = new Board();
        $this->board->setTitle('Test Board');
        $this->board->setOwner($this->user);
        $this->em->persist($this->board);

        $this->column = new Column('Test Column', '1000');
        $this->column->setBoard($this->board);
        $this->em->persist($this->column);

        $this->em->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        if ($this->em !== null && $this->em->isOpen()) {
            $tasks = $this->em->getRepository(Task::class)->findAll();
            foreach ($tasks as $task) {
                $this->em->remove($task);
            }
            
            $columns = $this->em->getRepository(Column::class)->findAll();
            foreach ($columns as $col) {
                $this->em->remove($col);
            }
            
            if ($this->board !== null) {
                $this->em->remove($this->board);
            }
            
            if ($this->user !== null) {
                $this->em->remove($this->user);
            }
            
            $this->em->flush();
            $this->em->close();
        }
    }

    public function test_full_task_lifecycle(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user);

        // Step 1: Create Task via API
        $client->request('POST', '/api/tasks', [], [], [], json_encode([
            'columnId' => $this->column->getId(),
            'title' => 'Test Task for Lifecycle',
            'description' => 'This task tests the full lifecycle',
            'tags' => ['test', 'lifecycle']
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('uuid', $responseData);
        $this->assertEquals('Test Task for Lifecycle', $responseData['title']);
        $this->assertEquals($this->column->getId(), $responseData['columnId']);
        $this->assertEquals('backlog', $responseData['status']);
        $this->assertEquals('1000.0000000000', $responseData['position']);

        $taskId = $responseData['id'];

        // Verify task exists in database
        $task = $this->em->getRepository(Task::class)->find($taskId);
        $this->assertNotNull($task);
        $this->assertEquals('Test Task for Lifecycle', $task->getTitle());
        $this->assertEquals(['tags' => ['test', 'lifecycle']], $task->getMetadata());

        // Step 2: Reorder Tasks
        $client->request('POST', '/api/tasks/reorder', [], [], [], json_encode([
            'columnId' => $this->column->getId(),
            'orderedIds' => [$taskId],
            'strategy' => 'bulk'
        ]));

        $this->assertResponseIsSuccessful();

        // Step 3: Get Board and verify task is present
        $client->request('GET', '/api/boards/' . $this->board->getId());
        
        $this->assertResponseIsSuccessful();
        $boardData = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('columns', $boardData);
        
        $foundTask = false;
        foreach ($boardData['columns'] as $column) {
            foreach ($column['tasks'] ?? [] as $taskData) {
                if ($taskData['id'] === $taskId) {
                    $foundTask = true;
                    $this->assertEquals('Test Task for Lifecycle', $taskData['title']);
                    break 2;
                }
            }
        }
        
        $this->assertTrue($foundTask, 'Task should be present in board response');
    }

    public function test_task_creation_validates_input(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user);

        // Test missing title
        $client->request('POST', '/api/tasks', [], [], [], json_encode([
            'columnId' => $this->column->getId(),
            'title' => ''
        ]));

        $this->assertResponseStatusCodeSame(422);

        // Test invalid column ID
        $client->request('POST', '/api/tasks', [], [], [], json_encode([
            'columnId' => 99999,
            'title' => 'Test Task'
        ]));

        $this->assertResponseStatusCodeSame(404);
    }

    public function test_task_creation_with_tags(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user);

        $client->request('POST', '/api/tasks', [], [], [], json_encode([
            'columnId' => $this->column->getId(),
            'title' => 'Task with Tags',
            'tags' => ['urgent', 'bug', 'high-priority']
        ]));

        $this->assertResponseStatusCodeSame(201);

        $responseData = json_decode($client->getResponse()->getContent(), true);
        
        $task = $this->em->getRepository(Task::class)->find($responseData['id']);
        $this->assertNotNull($task);
        $this->assertEquals(['tags' => ['urgent', 'bug', 'high-priority']], $task->getMetadata());
    }
}
