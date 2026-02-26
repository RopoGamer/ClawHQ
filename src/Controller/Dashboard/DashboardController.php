<?php

namespace App\Controller\Dashboard;

use App\Enum\TaskStatus;
use App\Repository\AgentRepository;
use App\Repository\TaskRepository;
use App\Repository\TaskNoteRepository;
use App\Service\AgentPresenceResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard', name: 'dashboard_')]
class DashboardController extends AbstractController
{
    #[Route('/agents', name: 'agents', methods: ['GET'])]
    public function agents(
        AgentRepository $agentRepository,
        TaskRepository $taskRepository,
        AgentPresenceResolver $presenceResolver,
    ): Response {
        $agents = $agentRepository->findForDashboard();

        $agentCards = [];
        foreach ($agents as $agent) {
            $agentCards[] = [
                'agent' => $agent,
                'effective_state' => $presenceResolver->effectiveState($agent),
                'seconds_since_seen' => $presenceResolver->secondsSinceLastSeen($agent),
                'counts' => $taskRepository->getStatusCounts($agent),
            ];
        }

        return $this->render('dashboard/agents.html.twig', [
            'agent_cards' => $agentCards,
        ]);
    }

    #[Route('/agents/{id<\d+>}/board', name: 'agent_board', methods: ['GET'])]
    public function board(
        int $id,
        AgentRepository $agentRepository,
        TaskRepository $taskRepository,
        AgentPresenceResolver $presenceResolver,
    ): Response {
        $agent = $agentRepository->find($id);
        if ($agent === null) {
            throw $this->createNotFoundException('Agent not found.');
        }

        $groupedTasks = $this->buildBoardData($agent, $taskRepository);

        return $this->render('dashboard/board.html.twig', [
            'agent' => $agent,
            'effective_state' => $presenceResolver->effectiveState($agent),
            'seconds_since_seen' => $presenceResolver->secondsSinceLastSeen($agent),
            'grouped_tasks' => $groupedTasks,
            'statuses' => TaskStatus::cases(),
        ]);
    }

    #[Route('/agents/{id<\d+>}/board/partial', name: 'agent_board_partial', methods: ['GET'])]
    public function boardPartial(
        int $id,
        AgentRepository $agentRepository,
        TaskRepository $taskRepository,
        AgentPresenceResolver $presenceResolver,
    ): Response {
        $agent = $agentRepository->find($id);
        if ($agent === null) {
            throw $this->createNotFoundException('Agent not found.');
        }

        return $this->render('dashboard/_board_columns.html.twig', [
            'agent' => $agent,
            'effective_state' => $presenceResolver->effectiveState($agent),
            'seconds_since_seen' => $presenceResolver->secondsSinceLastSeen($agent),
            'grouped_tasks' => $this->buildBoardData($agent, $taskRepository),
            'statuses' => TaskStatus::cases(),
        ]);
    }

    #[Route('/agents/{id<\d+>}/status', name: 'agent_status', methods: ['GET'])]
    public function status(
        int $id,
        AgentRepository $agentRepository,
        TaskRepository $taskRepository,
        AgentPresenceResolver $presenceResolver,
    ): Response {
        $agent = $agentRepository->find($id);
        if ($agent === null) {
            throw $this->createNotFoundException('Agent not found.');
        }

        return $this->render('dashboard/status.html.twig', [
            'agent' => $agent,
            'effective_state' => $presenceResolver->effectiveState($agent),
            'seconds_since_seen' => $presenceResolver->secondsSinceLastSeen($agent),
            'counts' => $taskRepository->getStatusCounts($agent),
        ]);
    }

    #[Route('/agents/{id<\d+>}/status/partial', name: 'agent_status_partial', methods: ['GET'])]
    public function statusPartial(
        int $id,
        AgentRepository $agentRepository,
        TaskRepository $taskRepository,
        AgentPresenceResolver $presenceResolver,
    ): Response {
        $agent = $agentRepository->find($id);
        if ($agent === null) {
            throw $this->createNotFoundException('Agent not found.');
        }

        return $this->render('dashboard/_status_content.html.twig', [
            'agent' => $agent,
            'effective_state' => $presenceResolver->effectiveState($agent),
            'seconds_since_seen' => $presenceResolver->secondsSinceLastSeen($agent),
            'counts' => $taskRepository->getStatusCounts($agent),
        ]);
    }

    #[Route('/tasks/{id<\d+>}', name: 'task_show', methods: ['GET'])]
    public function taskShow(int $id, TaskRepository $taskRepository, TaskNoteRepository $taskNoteRepository, Request $request): Response
    {
        $task = $taskRepository->find($id);
        if ($task === null) {
            throw $this->createNotFoundException('Task not found.');
        }

        return $this->render('dashboard/_task_detail.html.twig', [
            'task' => $task,
            'notes' => $taskNoteRepository->findBy(['task' => $task], ['createdAt' => 'DESC']),
            'is_fragment' => $request->isXmlHttpRequest(),
        ]);
    }

    /**
     * @return array<string, list<\App\Entity\Task>>
     */
    private function buildBoardData(\App\Entity\Agent $agent, TaskRepository $taskRepository): array
    {
        $grouped = [];
        foreach (TaskStatus::cases() as $status) {
            $grouped[$status->value] = $taskRepository->findByAgentAndStatus($agent, $status);
        }

        return $grouped;
    }
}
