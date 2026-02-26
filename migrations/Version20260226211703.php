<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260226211703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE agent (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(120) NOT NULL, display_name VARCHAR(160) DEFAULT NULL, api_token_hash VARCHAR(255) DEFAULT NULL, state VARCHAR(20) NOT NULL, current_work CLOB DEFAULT NULL, current_task_external_id VARCHAR(191) DEFAULT NULL, mood VARCHAR(80) DEFAULT NULL, status_note CLOB DEFAULT NULL, progress_percent INTEGER DEFAULT NULL, metadata CLOB NOT NULL, last_seen_at DATETIME DEFAULT NULL, registered_at DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_agent_name ON agent (name)');
        $this->addSql('CREATE TABLE task (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, external_id VARCHAR(191) NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, status VARCHAR(20) NOT NULL, requested_by VARCHAR(160) NOT NULL, priority VARCHAR(20) DEFAULT NULL, due_at DATETIME DEFAULT NULL, labels CLOB NOT NULL, source_ref VARCHAR(255) DEFAULT NULL, started_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, agent_id INTEGER NOT NULL, CONSTRAINT FK_527EDB253414710B FOREIGN KEY (agent_id) REFERENCES agent (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_527EDB253414710B ON task (agent_id)');
        $this->addSql('CREATE INDEX idx_task_status ON task (status)');
        $this->addSql('CREATE INDEX idx_task_requested_by ON task (requested_by)');
        $this->addSql('CREATE UNIQUE INDEX uniq_task_agent_external_id ON task (agent_id, external_id)');
        $this->addSql('CREATE TABLE task_note (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(20) NOT NULL, note CLOB NOT NULL, created_at DATETIME NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_BC0E6E6F8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_BC0E6E6F8DB60186 ON task_note (task_id)');
        $this->addSql('CREATE INDEX idx_task_note_created_at ON task_note (created_at)');
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "user" (email)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE task_note');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE agent');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
