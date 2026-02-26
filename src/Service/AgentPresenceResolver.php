<?php

namespace App\Service;

use App\Entity\Agent;
use App\Enum\AgentState;

class AgentPresenceResolver
{
    public function effectiveState(Agent $agent, ?\DateTimeImmutable $now = null): AgentState
    {
        return $agent->getState();
    }

    public function secondsSinceLastSeen(Agent $agent, ?\DateTimeImmutable $now = null): ?int
    {
        $lastSeenAt = $agent->getLastSeenAt();
        if ($lastSeenAt === null) {
            return null;
        }

        $now ??= new \DateTimeImmutable();

        return max(0, $now->getTimestamp() - $lastSeenAt->getTimestamp());
    }
}
