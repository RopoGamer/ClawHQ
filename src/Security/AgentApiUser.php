<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class AgentApiUser implements UserInterface
{
    public function __construct(
        private readonly int $agentId,
        private readonly string $agentName,
    ) {
    }

    public function getAgentId(): int
    {
        return $this->agentId;
    }

    public function getRoles(): array
    {
        return ['ROLE_AGENT'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return sprintf('agent:%d:%s', $this->agentId, $this->agentName);
    }
}
