<?php

namespace App\Controller\Api;

use App\Entity\Agent;
use App\Repository\AgentRepository;
use App\Service\RegistrationThrottle;
use App\Service\AgentTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/api/v1/agents', name: 'api_agents_')]
class AgentRegistrationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AgentRepository $agentRepository,
        private readonly AgentTokenManager $tokenManager,
        private readonly RegistrationThrottle $registrationThrottle,
        #[Autowire('%app.agent_registration_passphrase%')]
        private readonly string $agentRegistrationPassphrase,
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        if ($payload === null) {
            return $this->jsonError('invalid_json', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        $agentName = isset($payload['agent_name']) ? trim((string) $payload['agent_name']) : '';
        $passphrase = isset($payload['passphrase']) ? (string) $payload['passphrase'] : '';

        if ($agentName === '' || $passphrase === '') {
            return $this->jsonError('validation_error', 'Fields "agent_name" and "passphrase" are required.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $rateLimit = $this->registrationThrottle->consume(
            sprintf('%s:%s', $request->getClientIp() ?? 'unknown', strtolower($agentName))
        );
        if (!$rateLimit['accepted']) {
            $headers = ['Retry-After' => (int) ($rateLimit['retry_after'] ?? 1)];

            return new JsonResponse([
                'error' => 'rate_limited',
                'message' => 'Too many registration requests. Try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS, $headers);
        }

        if (!hash_equals($this->agentRegistrationPassphrase, $passphrase)) {
            return $this->jsonError('invalid_passphrase', 'Registration passphrase is invalid.', Response::HTTP_UNAUTHORIZED);
        }

        $agent = $this->agentRepository->findByNormalizedName($agentName);
        if ($agent === null) {
            $agent = (new Agent())
                ->setName($agentName)
                ->setDisplayName($payload['display_name'] ?? null);
            $this->entityManager->persist($agent);
            $this->entityManager->flush();
        }

        if (array_key_exists('display_name', $payload)) {
            $agent->setDisplayName($payload['display_name'] !== null ? (string) $payload['display_name'] : null);
        }

        if (isset($payload['metadata']) && is_array($payload['metadata'])) {
            $agent->setMetadata($payload['metadata']);
        }

        $agent->touch();
        $token = $this->tokenManager->createAndStoreToken($agent);

        $this->entityManager->persist($agent);
        $this->entityManager->flush();

        return new JsonResponse([
            'agent_id' => $agent->getId(),
            'agent_name' => $agent->getName(),
            'token' => $token,
            'token_type' => 'Bearer',
            'api_base' => $request->getSchemeAndHttpHost().'/api/v1',
            'skill_url' => $this->generateUrl('app_clawhq_skill_md', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'heartbeat_seconds' => 1800,
            'poll_seconds' => 5,
        ], Response::HTTP_CREATED);
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

    private function jsonError(string $error, string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'error' => $error,
            'message' => $message,
        ], $status);
    }
}
