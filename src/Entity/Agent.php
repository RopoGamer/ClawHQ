<?php

namespace App\Entity;

use App\Enum\AgentState;
use App\Repository\AgentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgentRepository::class)]
#[ORM\Table(name: 'agent')]
#[ORM\UniqueConstraint(name: 'uniq_agent_name', columns: ['name'])]
#[ORM\HasLifecycleCallbacks]
class Agent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $apiTokenHash = null;

    #[ORM\Column(length: 20, enumType: AgentState::class)]
    private AgentState $state = AgentState::IDLE;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $currentWork = null;

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $currentTaskExternalId = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $mood = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $statusNote = null;

    #[ORM\Column(nullable: true)]
    private ?int $progressPercent = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $registeredAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, Task>
     */
    #[ORM\OneToMany(mappedBy: 'agent', targetEntity: Task::class, orphanRemoval: true)]
    #[ORM\OrderBy(['updatedAt' => 'DESC'])]
    private Collection $tasks;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->registeredAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->tasks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = strtolower(trim($name));

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): static
    {
        $this->displayName = $displayName !== null ? trim($displayName) : null;

        return $this;
    }

    public function getApiTokenHash(): ?string
    {
        return $this->apiTokenHash;
    }

    public function setApiTokenHash(?string $apiTokenHash): static
    {
        $this->apiTokenHash = $apiTokenHash;

        return $this;
    }

    public function getState(): AgentState
    {
        return $this->state;
    }

    public function setState(AgentState $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getCurrentWork(): ?string
    {
        return $this->currentWork;
    }

    public function setCurrentWork(?string $currentWork): static
    {
        $this->currentWork = $currentWork;

        return $this;
    }

    public function getCurrentTaskExternalId(): ?string
    {
        return $this->currentTaskExternalId;
    }

    public function setCurrentTaskExternalId(?string $currentTaskExternalId): static
    {
        $this->currentTaskExternalId = $currentTaskExternalId;

        return $this;
    }

    public function getMood(): ?string
    {
        return $this->mood;
    }

    public function setMood(?string $mood): static
    {
        $this->mood = $mood;

        return $this;
    }

    public function getStatusNote(): ?string
    {
        return $this->statusNote;
    }

    public function setStatusNote(?string $statusNote): static
    {
        $this->statusNote = $statusNote;

        return $this;
    }

    public function getProgressPercent(): ?int
    {
        return $this->progressPercent;
    }

    public function setProgressPercent(?int $progressPercent): static
    {
        if ($progressPercent !== null) {
            $progressPercent = max(0, min(100, $progressPercent));
        }

        $this->progressPercent = $progressPercent;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): static
    {
        $this->lastSeenAt = $lastSeenAt;

        return $this;
    }

    public function getRegisteredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function setRegisteredAt(\DateTimeImmutable $registeredAt): static
    {
        $this->registeredAt = $registeredAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setAgent($this);
        }

        return $this;
    }

    public function removeTask(Task $task): static
    {
        if ($this->tasks->removeElement($task)) {
            if ($task->getAgent() === $this) {
                $task->setAgent(null);
            }
        }

        return $this;
    }

    public function touch(?\DateTimeImmutable $at = null): static
    {
        $this->lastSeenAt = $at ?? new \DateTimeImmutable();
        $this->updatedAt = $this->lastSeenAt;

        return $this;
    }

    #[ORM\PreUpdate]
    public function bumpUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
