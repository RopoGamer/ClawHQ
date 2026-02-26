<?php

namespace App\Entity;

use App\Enum\TaskNoteType;
use App\Repository\TaskNoteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskNoteRepository::class)]
#[ORM\Table(name: 'task_note')]
#[ORM\Index(name: 'idx_task_note_created_at', columns: ['created_at'])]
class TaskNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'notes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Task $task = null;

    #[ORM\Column(length: 20, enumType: TaskNoteType::class)]
    private TaskNoteType $type = TaskNoteType::PROGRESS;

    #[ORM\Column(type: 'text')]
    private string $note;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTask(): ?Task
    {
        return $this->task;
    }

    public function setTask(?Task $task): static
    {
        $this->task = $task;

        return $this;
    }

    public function getType(): TaskNoteType
    {
        return $this->type;
    }

    public function setType(TaskNoteType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
