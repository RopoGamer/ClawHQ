<?php

namespace App\Tests\Dashboard;

use App\Entity\Agent;
use App\Entity\Task;
use App\Entity\TaskNote;
use App\Entity\User;
use App\Enum\AgentState;
use App\Enum\TaskNoteType;
use App\Enum\TaskStatus;
use App\Tests\Support\ResetsDatabaseTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DashboardAccessTest extends WebTestCase
{
    use ResetsDatabaseTrait;

    protected function setUp(): void
    {
        parent::setUp();
        static::createClient();
        $this->resetDatabase();
        self::ensureKernelShutdown();
    }

    public function testDashboardRequiresAuthenticationAndRendersForLoggedInUser(): void
    {
        $client = static::createClient();

        $client->request('GET', '/dashboard/agents');
        self::assertResponseRedirects('/login');

        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();

        $user = (new User())->setEmail('owner@example.com');
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $user->setPassword($passwordHasher->hashPassword($user, 'secret123'));
        $entityManager->persist($user);

        $agent = (new Agent())
            ->setName('openclaw-main')
            ->setDisplayName('OpenClaw Main')
            ->setState(AgentState::WORKING)
            ->setCurrentWork('Implementing dashboard')
            ->setMood('locked-in')
            ->setStatusNote('Building card view')
            ->touch();
        $entityManager->persist($agent);

        $task = (new Task())
            ->setAgent($agent)
            ->setExternalId('TASK-7')
            ->setTitle('Build dashboard page')
            ->setStatus(TaskStatus::DOING)
            ->setRequestedBy('roan')
            ->setDescription('Create board and status page')
            ->touch();
        $entityManager->persist($task);

        $note = (new TaskNote())
            ->setTask($task)
            ->setType(TaskNoteType::PROGRESS)
            ->setNote('Board and status routes are online');
        $entityManager->persist($note);

        $entityManager->flush();

        $client->loginUser($user);

        $client->request('GET', '/dashboard/agents');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Team Roster');

        $client->request('GET', '/dashboard/agents/'.$agent->getId().'/board');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', "OpenClaw Main's Corkboard");

        $client->request('GET', '/dashboard/agents/'.$agent->getId().'/status');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'OpenClaw Main Personnel File');

        $client->request('GET', '/dashboard/tasks/'.$task->getId(), server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Build dashboard page', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Board and status routes are online', (string) $client->getResponse()->getContent());
    }
}
