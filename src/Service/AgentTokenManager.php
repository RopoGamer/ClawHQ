<?php

namespace App\Service;

use App\Entity\Agent;

class AgentTokenManager
{
    private const TOKEN_PREFIX = 'chq_';

    public function createAndStoreToken(Agent $agent): string
    {
        $id = $agent->getId();
        if ($id === null) {
            throw new \LogicException('Cannot issue API token for an unsaved agent.');
        }

        $secret = $this->generateSecret();
        $token = $this->buildToken($id, $secret);
        $agent->setApiTokenHash(password_hash($secret, PASSWORD_DEFAULT));

        return $token;
    }

    public function verifyTokenForAgent(Agent $agent, string $token): bool
    {
        $parts = $this->parseToken($token);
        if ($parts === null) {
            return false;
        }

        [$agentId, $secret] = $parts;
        if ($agent->getId() !== $agentId) {
            return false;
        }

        $hash = $agent->getApiTokenHash();
        if ($hash === null || $hash === '') {
            return false;
        }

        return password_verify($secret, $hash);
    }

    public function extractAgentId(string $token): ?int
    {
        $parts = $this->parseToken($token);

        return $parts[0] ?? null;
    }

    private function generateSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function buildToken(int $agentId, string $secret): string
    {
        return sprintf('%s%d.%s', self::TOKEN_PREFIX, $agentId, $secret);
    }

    /**
     * @return array{0: int, 1: string}|null
     */
    private function parseToken(string $token): ?array
    {
        if (!preg_match('/^'.self::TOKEN_PREFIX.'(\\d+)\\.([A-Za-z0-9_-]{24,})$/', trim($token), $matches)) {
            return null;
        }

        return [(int) $matches[1], $matches[2]];
    }
}
