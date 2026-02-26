<?php

namespace App\Tests\Api;

use App\Repository\TaskRepository;
use App\Tests\Support\ResetsDatabaseTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AgentApiTest extends WebTestCase
{
    use ResetsDatabaseTrait;

    protected function setUp(): void
    {
        parent::setUp();
        static::createClient();
        $this->resetDatabase();
        self::ensureKernelShutdown();
    }

    public function testRegisterStatusTaskNoteAndTokenRotationFlow(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/agents/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'agent_name' => 'openclaw-main',
            'passphrase' => 'change-me',
            'display_name' => 'OpenClaw Main',
            'metadata' => ['provider' => 'openclaw'],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(201);
        $registerPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $registerPayload);
        $token = $registerPayload['token'];

        $client->request('PUT', '/api/v1/agents/me/status', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'state' => 'working',
            'current_work' => 'Implementing API layer',
            'mood' => 'focused',
            'progress_percent' => 25,
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(401);

        $authHeaders = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ];

        $client->request('PUT', '/api/v1/agents/me/status', server: $authHeaders, content: json_encode([
            'state' => 'working',
            'current_work' => 'Implementing API layer',
            'mood' => 'focused',
            'progress_percent' => 25,
            'status_note' => 'flow running',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $client->request('PUT', '/api/v1/agents/me/tasks/TASK-42', server: $authHeaders, content: json_encode([
            'title' => 'Implement API auth',
            'description' => 'Add bearer security',
            'status' => 'doing',
            'requested_by' => 'roan',
            'priority' => 'high',
            'labels' => ['security', 'api'],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $firstTaskPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $taskId = $firstTaskPayload['task']['id'];

        $client->request('PUT', '/api/v1/agents/me/tasks/TASK-42', server: $authHeaders, content: json_encode([
            'title' => 'Implement API auth done',
            'status' => 'done',
            'requested_by' => 'roan',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $secondTaskPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($taskId, $secondTaskPayload['task']['id']);

        $client->request('POST', '/api/v1/agents/me/tasks/TASK-42/notes', server: $authHeaders, content: json_encode([
            'type' => 'progress',
            'note' => 'Finished API auth integration',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/v1/agents/me/token/rotate', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseIsSuccessful();
        $rotatePayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $newToken = $rotatePayload['token'];

        $client->request('PUT', '/api/v1/agents/me/status', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['state' => 'idle'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(401);

        $client->request('PUT', '/api/v1/agents/me/status', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$newToken,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['state' => 'idle'], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        /** @var TaskRepository $taskRepository */
        $taskRepository = static::getContainer()->get(TaskRepository::class);
        self::assertCount(1, $taskRepository->findAll());
    }
}
