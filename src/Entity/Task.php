<?php

namespace App\Entity;

use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'task')]
#[ORM\UniqueConstraint(name: 'uniq_task_agent_external_id', columns: ['agent_id', 'external_id'])]
#[ORM\Index(name: 'idx_task_status', columns: ['status'])]
#[ORM\Index(name: 'idx_task_requested_by', columns: ['requested_by'])]
#[ORM\HasLifecycleCallbacks]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Agent $agent = null;

    #[ORM\Column(length: 191)]
    private string $externalId;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: TaskStatus::class)]
    private TaskStatus $status = TaskStatus::TODO;

    #[ORM\Column(length: 160)]
    private string $requestedBy;

    #[ORM\Column(length: 20, enumType: TaskPriority::class, nullable: true)]
    private ?TaskPriority $priority = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $labels = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceRef = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, TaskNote>
     */
    #[ORM\OneToMany(mappedBy: 'task', targetEntity: TaskNote::class, orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $notes;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->notes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgent(): ?Agent
    {
        return $this->agent;
    }

    public function setAgent(?Agent $agent): static
    {
        $this->agent = $agent;

        return $this;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): static
    {
        $this->externalId = trim($externalId);

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = trim($title);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function setStatus(TaskStatus $status): static
    {
        $previous = $this->status;
        $this->status = $status;

        if ($status === TaskStatus::DOING && $this->startedAt === null) {
            $this->startedAt = new \DateTimeImmutable();
        }

        if ($status === TaskStatus::DONE) {
            $this->completedAt = new \DateTimeImmutable();
            if ($this->startedAt === null) {
                $this->startedAt = $this->completedAt;
            }
        }

        if ($previous === TaskStatus::DONE && $status !== TaskStatus::DONE) {
            $this->completedAt = null;
        }

        return $this;
    }

    public function getRequestedBy(): string
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(string $requestedBy): static
    {
        $this->requestedBy = trim($requestedBy);

        return $this;
    }

    public function getPriority(): ?TaskPriority
    {
        return $this->priority;
    }

    public function setPriority(?TaskPriority $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getDueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(?\DateTimeImmutable $dueAt): static
    {
        $this->dueAt = $dueAt;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * @param list<string> $labels
     */
    public function setLabels(array $labels): static
    {
        $labels = array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $labels
        )));

        $this->labels = array_values(array_unique($labels));

        return $this;
    }

    public function getSourceRef(): ?string
    {
        return $this->sourceRef;
    }

    public function setSourceRef(?string $sourceRef): static
    {
        $this->sourceRef = $sourceRef;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

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
     * @return Collection<int, TaskNote>
     */
    public function getNotes(): Collection
    {
        return $this->notes;
    }

    public function addNote(TaskNote $note): static
    {
        if (!$this->notes->contains($note)) {
            $this->notes->add($note);
            $note->setTask($this);
        }

        return $this;
    }

    public function removeNote(TaskNote $note): static
    {
        if ($this->notes->removeElement($note)) {
            if ($note->getTask() === $this) {
                $note->setTask(null);
            }
        }

        return $this;
    }

    #[ORM\PreUpdate]
    public function bumpUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
