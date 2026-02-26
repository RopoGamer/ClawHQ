<?php

namespace App\Controller\Api;

use App\Entity\Agent;
use App\Entity\Task;
use App\Entity\TaskNote;
use App\Enum\AgentState;
use App\Enum\TaskNoteType;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Repository\AgentRepository;
use App\Repository\TaskRepository;
use App\Security\AgentApiUser;
use App\Service\AgentTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/agents/me', name: 'api_agents_me_')]
class AgentMeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AgentRepository $agentRepository,
        private readonly TaskRepository $taskRepository,
        private readonly AgentTokenManager $tokenManager,
    ) {
    }

    #[Route('/token/rotate', name: 'rotate_token', methods: ['POST'])]
    public function rotateToken(): JsonResponse
    {
        $agent = $this->requireCurrentAgent();
        if ($agent === null) {
            return $this->jsonError('unauthorized', 'Invalid API identity.', Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->tokenManager->createAndStoreToken($agent);
        $agent->touch();
        $this->entityManager->flush();

        return new JsonResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'rotated_at' => $agent->getLastSeenAt()?->format(DATE_ATOM),
        ]);
    }

    #[Route('/status', name: 'status_upsert', methods: ['PUT'])]
    public function upsertStatus(Request $request): JsonResponse
    {
        $agent = $this->requireCurrentAgent();
        if ($agent === null) {
            return $this->jsonError('unauthorized', 'Invalid API identity.', Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->decodeJson($request);
        if ($payload === null) {
            return $this->jsonError('invalid_json', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        if (!isset($payload['state'])) {
            return $this->jsonError('validation_error', 'Field "state" is required.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $state = AgentState::tryFrom((string) $payload['state']);
        if ($state === null) {
            return $this->jsonError('validation_error', 'Field "state" is invalid.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $agent->setState($state);

        if (array_key_exists('current_work', $payload)) {
            $agent->setCurrentWork($payload['current_work'] !== null ? (string) $payload['current_work'] : null);
        }
        if (array_key_exists('current_task_external_id', $payload)) {
            $agent->setCurrentTaskExternalId($payload['current_task_external_id'] !== null ? (string) $payload['current_task_external_id'] : null);
        }
        if (array_key_exists('mood', $payload)) {
            $agent->setMood($payload['mood'] !== null ? (string) $payload['mood'] : null);
        }
        if (array_key_exists('status_note', $payload)) {
            $agent->setStatusNote($payload['status_note'] !== null ? (string) $payload['status_note'] : null);
        }
        if (array_key_exists('progress_percent', $payload)) {
            $progress = $payload['progress_percent'];
            if ($progress !== null && !is_numeric($progress)) {
                return $this->jsonError('validation_error', 'Field "progress_percent" must be a number or null.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $agent->setProgressPercent($progress !== null ? (int) $progress : null);
        }

        $agent->touch();
        $this->entityManager->flush();

        return new JsonResponse([
            'agent' => $this->serializeAgent($agent),
        ]);
    }

    #[Route('/tasks/{externalTaskId}', name: 'task_upsert', methods: ['PUT'])]
    public function upsertTask(string $externalTaskId, Request $request): JsonResponse
    {
        $agent = $this->requireCurrentAgent();
        if ($agent === null) {
            return $this->jsonError('unauthorized', 'Invalid API identity.', Response::HTTP_UNAUTHORIZED);
        }

        $externalTaskId = trim($externalTaskId);
        if ($externalTaskId === '') {
            return $this->jsonError('validation_error', 'Task external id must not be empty.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $payload = $this->decodeJson($request);
        if ($payload === null) {
            return $this->jsonError('invalid_json', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        $title = isset($payload['title']) ? trim((string) $payload['title']) : '';
        $status = isset($payload['status']) ? TaskStatus::tryFrom((string) $payload['status']) : null;
        $requestedBy = isset($payload['requested_by']) ? trim((string) $payload['requested_by']) : '';

        if ($title === '' || $status === null || $requestedBy === '') {
            return $this->jsonError(
                'validation_error',
                'Fields "title", "status" (todo|doing|done) and "requested_by" are required.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $task = $this->taskRepository->findOneByAgentAndExternalId($agent, $externalTaskId);
        if ($task === null) {
            $task = (new Task())
                ->setAgent($agent)
                ->setExternalId($externalTaskId);
            $this->entityManager->persist($task);
        }

        $task->setTitle($title)
            ->setStatus($status)
            ->setRequestedBy($requestedBy)
            ->setDescription($payload['description'] ?? null)
            ->setSourceRef($payload['source_ref'] ?? null)
            ->touch();

        if (array_key_exists('priority', $payload) && $payload['priority'] !== null) {
            $priority = TaskPriority::tryFrom((string) $payload['priority']);
            if ($priority === null) {
                return $this->jsonError('validation_error', 'Field "priority" is invalid.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $task->setPriority($priority);
        } elseif (array_key_exists('priority', $payload)) {
            $task->setPriority(null);
        }

        if (array_key_exists('due_at', $payload)) {
            if ($payload['due_at'] === null || $payload['due_at'] === '') {
                $task->setDueAt(null);
            } else {
                try {
                    $task->setDueAt(new \DateTimeImmutable((string) $payload['due_at']));
                } catch (\Exception) {
                    return $this->jsonError('validation_error', 'Field "due_at" must be a valid ISO date.', Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }
        }

        if (isset($payload['labels']) && is_array($payload['labels'])) {
            $task->setLabels($payload['labels']);
        } elseif (array_key_exists('labels', $payload)) {
            return $this->jsonError('validation_error', 'Field "labels" must be an array of strings.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $agent->touch();
        $this->entityManager->flush();

        return new JsonResponse([
            'task' => $this->serializeTask($task),
        ]);
    }

    #[Route('/tasks/{externalTaskId}/notes', name: 'task_note_create', methods: ['POST'])]
    public function createTaskNote(string $externalTaskId, Request $request): JsonResponse
    {
        $agent = $this->requireCurrentAgent();
        if ($agent === null) {
            return $this->jsonError('unauthorized', 'Invalid API identity.', Response::HTTP_UNAUTHORIZED);
        }

        $task = $this->taskRepository->findOneByAgentAndExternalId($agent, $externalTaskId);
        if ($task === null) {
            return $this->jsonError('not_found', 'Task not found for this agent.', Response::HTTP_NOT_FOUND);
        }

        $payload = $this->decodeJson($request);
        if ($payload === null) {
            return $this->jsonError('invalid_json', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        $noteText = isset($payload['note']) ? trim((string) $payload['note']) : '';
        if ($noteText === '') {
            return $this->jsonError('validation_error', 'Field "note" is required.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $noteType = TaskNoteType::PROGRESS;
        if (isset($payload['type'])) {
            $noteType = TaskNoteType::tryFrom((string) $payload['type']);
            if ($noteType === null) {
                return $this->jsonError('validation_error', 'Field "type" is invalid.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $note = (new TaskNote())
            ->setTask($task)
            ->setType($noteType)
            ->setNote($noteText);

        $task->touch();
        $agent->touch();

        $this->entityManager->persist($note);
        $this->entityManager->flush();

        return new JsonResponse([
            'note' => [
                'id' => $note->getId(),
                'task_id' => $task->getId(),
                'type' => $note->getType()->value,
                'note' => $note->getNote(),
                'created_at' => $note->getCreatedAt()->format(DATE_ATOM),
            ],
        ], Response::HTTP_CREATED);
    }

    private function requireCurrentAgent(): ?Agent
    {
        $user = $this->getUser();
        if (!$user instanceof AgentApiUser) {
            return null;
        }

        return $this->agentRepository->find($user->getAgentId());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(Request $request): ?array
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAgent(Agent $agent): array
    {
        return [
            'id' => $agent->getId(),
            'name' => $agent->getName(),
            'display_name' => $agent->getDisplayName(),
            'state' => $agent->getState()->value,
            'current_work' => $agent->getCurrentWork(),
            'current_task_external_id' => $agent->getCurrentTaskExternalId(),
            'mood' => $agent->getMood(),
            'status_note' => $agent->getStatusNote(),
            'progress_percent' => $agent->getProgressPercent(),
            'last_seen_at' => $agent->getLastSeenAt()?->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTask(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'agent_id' => $task->getAgent()?->getId(),
            'external_id' => $task->getExternalId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus()->value,
            'requested_by' => $task->getRequestedBy(),
            'priority' => $task->getPriority()?->value,
            'due_at' => $task->getDueAt()?->format(DATE_ATOM),
            'labels' => $task->getLabels(),
            'source_ref' => $task->getSourceRef(),
            'started_at' => $task->getStartedAt()?->format(DATE_ATOM),
            'completed_at' => $task->getCompletedAt()?->format(DATE_ATOM),
            'updated_at' => $task->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function jsonError(string $error, string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'error' => $error,
            'message' => $message,
        ], $status);
    }
}
