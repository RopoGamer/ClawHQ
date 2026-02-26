<?php

namespace App\Security;

use App\Repository\AgentRepository;
use App\Service\AgentTokenManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class AgentTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly AgentRepository $agentRepository,
        private readonly AgentTokenManager $tokenManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/api/v1/agents/me');
    }

    public function authenticate(Request $request): Passport
    {
        $authorization = $request->headers->get('Authorization');
        if ($authorization === null || !preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            throw new CustomUserMessageAuthenticationException('Missing bearer token.');
        }

        $token = trim($matches[1]);
        $agentId = $this->tokenManager->extractAgentId($token);
        if ($agentId === null) {
            throw new CustomUserMessageAuthenticationException('Invalid API token.');
        }

        $agent = $this->agentRepository->find($agentId);
        if ($agent === null || !$this->tokenManager->verifyTokenForAgent($agent, $token)) {
            throw new CustomUserMessageAuthenticationException('Invalid API token.');
        }

        return new SelfValidatingPassport(new UserBadge(
            sprintf('agent:%d', $agent->getId()),
            fn () => new AgentApiUser($agentId, $agent->getName()),
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => 'unauthorized',
            'message' => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse([
            'error' => 'unauthorized',
            'message' => 'Authentication required.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
