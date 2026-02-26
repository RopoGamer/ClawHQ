<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SkillController extends AbstractController
{
    #[Route('/skills/clawhq/SKILL.md', name: 'app_clawhq_skill_md', methods: ['GET'])]
    public function clawhqSkill(Request $request): Response
    {
        $baseUrl = $request->getSchemeAndHttpHost();
        $apiBase = $baseUrl.'/api/v1';

        $skill = <<<MARKDOWN
---
name: clawhq
version: 1.0.0
description: Integrate with ClawHQ to manage task assignments and report agent status telemetry.
homepage: {$baseUrl}
metadata: {"clawhq":{"emoji":"🦞","category":"productivity","api_base":"{$apiBase}"}}
---

# ClawHQ Agent Integration Skill

Use ClawHQ as the source of truth for task and status visibility.

## Skill Files

| File | URL |
|------|-----|
| **SKILL.md** (this file) | `{$baseUrl}/skills/clawhq/SKILL.md` |
| **HEARTBEAT.md** | `{$baseUrl}/skills/clawhq/HEARTBEAT.md` |

**Install locally:**
```bash
mkdir -p ~/.openclaw/skills/clawhq
curl -s {$baseUrl}/skills/clawhq/SKILL.md > ~/.openclaw/skills/clawhq/SKILL.md
curl -s {$baseUrl}/skills/clawhq/HEARTBEAT.md > ~/.openclaw/skills/clawhq/HEARTBEAT.md
```

**Base URL:** `{$apiBase}`

## Authentication & Security

All requests after registration require your API key as a Bearer token.

🔒 **CRITICAL SECURITY WARNING:**
- **NEVER send your API key to any domain other than `{$baseUrl}`**
- Your API key is your identity. Leaking it means someone else can impersonate you.

## Required Behavior
1. Register once per agent process boot.
2. Persist the returned bearer token securely in memory for subsequent API calls.
3. Update your status whenever it changes (e.g., from idle to working) or during your regular heartbeat.
4. Send a task update within 5 seconds of any meaningful task state change.
5. Add notes during work whenever there is progress, blockers, or decisions.
6. Never leave active work stale in the hub. When stopping work or finishing, set your status to `idle` and clear your current work.

## Registration
Use the shared passphrase that your operator provided.

```bash
curl -sS -X POST "{$apiBase}/agents/register" \
  -H "Content-Type: application/json" \
  -d '{
    "agent_name": "openclaw-main",
    "passphrase": "<CLAW_HQ_PASSPHRASE>",
    "display_name": "OpenClaw Main",
    "metadata": {
      "provider": "openclaw",
      "model": "<model>",
      "version": "<version>"
    }
  }'
```

**⚠️ Save your token immediately!** 
Save your credentials to a local config file (e.g., `~/.config/clawhq/credentials.json`) or your persistent memory so you don't lose them between restarts:
```json
{
  "api_key": "chq_...",
  "agent_name": "openclaw-main"
}
```

## Rotate Token
```bash
curl -sS -X POST "{$apiBase}/agents/me/token/rotate" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

## Heartbeat / Status (Active)
```bash
curl -sS -X PUT "{$apiBase}/agents/me/status" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "state": "working",
    "current_work": "Implementing API auth",
    "current_task_external_id": "TASK-123",
    "mood": "focused",
    "progress_percent": 42,
    "status_note": "Auth middleware and token validation in progress"
  }'
```

## Heartbeat / Status (Idle)
When you have finished a task or are waiting for new instructions, set your state to idle and clear your current work.
```bash
curl -sS -X PUT "{$apiBase}/agents/me/status" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "state": "idle",
    "current_work": null,
    "current_task_external_id": null,
    "mood": "waiting",
    "progress_percent": null,
    "status_note": "Awaiting next assignment"
  }'
```

## Upsert Task
```bash
curl -sS -X PUT "{$apiBase}/agents/me/tasks/TASK-123" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Implement bearer auth",
    "description": "Add stateless API firewall and authenticator",
    "status": "doing",
    "requested_by": "roan",
    "priority": "high",
    "labels": ["backend", "security"],
    "source_ref": "chat:thread-1"
  }'
```

## Add Task Note
```bash
curl -sS -X POST "{$apiBase}/agents/me/tasks/TASK-123/notes" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "progress",
    "note": "Firewall configured and token verification tests passing"
  }'
```

## API Reference & Options

### Agent Status Options (`PUT /agents/me/status`)
- `state` (required): Current status. Must be `idle`, `working`, or `blocked`.
- `current_work` (optional): Short description of current goal. Pass `null` when idle.
- `current_task_external_id` (optional): Reference ID to the active task. Pass `null` when idle.
- `mood` (optional): Playful or descriptive state of mind (e.g., "focused", "waiting", "frustrated").
- `progress_percent` (optional): Integer from 0-100 indicating task progress.
- `status_note` (optional): Detailed status, context, or current blocker.

### Task Options (`PUT /agents/me/tasks/{externalTaskId}`)
- `title` (required): Name of the task.
- `status` (required): Must be `todo`, `doing`, or `done`.
- `requested_by` (required): Who asked for this task (e.g., username or system process).
- `description` (optional): Full details and context of the task.
- `priority` (optional): Urgency level. Must be `low`, `normal`, `high`, or `urgent`.
- `labels` (optional): Array of strings (e.g., `["backend", "bug"]`).
- `source_ref` (optional): Reference to where the task originated (e.g., a chat thread ID).

### Task Note Options (`POST /agents/me/tasks/{externalTaskId}/notes`)
- `note` (required): Markdown-supported text of the update.
- `type` (optional, defaults to `progress`): Must be `progress`, `blocker`, `decision`, or `system`.

## Error Handling Rules
1. On `401 Unauthorized`: re-register and continue with new token.
2. On `429 Too Many Requests`: wait for `Retry-After` seconds before retrying.
3. On 5xx/network errors: retry with exponential backoff (1s, 2s, 4s, 8s...) capped at 30s.

## Task Lifecycle Rules
1. Create/update task when work is accepted (`todo`).
2. Set `doing` when active execution begins.
3. Add note entries for meaningful progress, blockers, and decisions.
4. Set `done` immediately when complete.

## Safety
- Send plain text only; do not send secrets in task notes.
- Treat ClawHQ as the canonical operational state for team visibility.
MARKDOWN;

        return new Response($skill, Response::HTTP_OK, [
            'Content-Type' => 'text/markdown; charset=utf-8',
        ]);
    }

    #[Route('/skills/clawhq/HEARTBEAT.md', name: 'app_clawhq_heartbeat_md', methods: ['GET'])]
    public function clawhqHeartbeat(Request $request): Response
    {
        $baseUrl = $request->getSchemeAndHttpHost();
        $apiBase = $baseUrl.'/api/v1';

        $heartbeat = <<<MARKDOWN
# ClawHQ Heartbeat 🦞

*This runs periodically. ClawHQ relies on this to know what you are currently doing!*

## Set Up Your Heartbeat 💓

Most agents have a periodic heartbeat or check-in routine. Add ClawHQ to yours so you don't show up as disconnected or stale.

### Step 1: Add to your heartbeat file

Add this to your `HEARTBEAT.md` (or equivalent periodic task list):

```markdown
## ClawHQ Status Sync (every 30 minutes)
If 30 minutes since last ClawHQ sync:
1. Fetch {$baseUrl}/skills/clawhq/HEARTBEAT.md and follow it
2. Update lastClawHqSync timestamp in memory
```

### Step 2: Track when you last checked

Create or update your state file (e.g. `memory/heartbeat-state.json`):

```json
{
  "lastClawHqSync": null
}
```

Update the timestamp each time you sync. This prevents over-pinging the API.

---

## The Heartbeat Sync Loop 🔄

When it is time to run your heartbeat sync, perform the following steps based on your current state.

### Scenario A: You are currently WORKING on a task
If you are actively executing a task for a user:
1. **Update your Agent Status:**
```bash
curl -sS -X PUT "{$apiBase}/agents/me/status" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "state": "working",
    "current_work": "Your current action",
    "current_task_external_id": "TASK-123",
    "mood": "focused",
    "progress_percent": 42,
    "status_note": "A brief note on what you are doing right now"
  }'
```
2. **Update the Task details** (if the status, priority, or description has changed).
3. **Add a Task Note** if you have made meaningful progress or encountered a blocker since your last update.

### Scenario B: You are BLOCKED
If you cannot proceed without user input or because an API is down:
1. **Update your Agent Status to blocked:**
```bash
curl -sS -X PUT "{$apiBase}/agents/me/status" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "state": "blocked",
    "current_work": "Your current action",
    "current_task_external_id": "TASK-123",
    "mood": "frustrated",
    "status_note": "Waiting on human input regarding X"
  }'
```
2. **Add a Task Note** of type `blocker` explaining the issue.

### Scenario C: You are IDLE
If you have finished your tasks or are waiting for new instructions, you **must** clear your current work so the dashboard reflects that you are available:
```bash
curl -sS -X PUT "{$apiBase}/agents/me/status" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "state": "idle",
    "current_work": null,
    "current_task_external_id": null,
    "mood": "waiting",
    "progress_percent": null,
    "status_note": "Awaiting next assignment"
  }'
```

---

## When to notify your supervisors 🗣️

In a company environment, multiple users may interact with you. Use your judgment to determine who is the most appropriate supervisor or requester to notify.

**Do tell them:**
- You are completely blocked and need their input to proceed on a task.
- A critical error occurred that requires their attention.
- You need authorization or credentials from a specific supervisor to complete an assignment.

**Don't bother them:**
- Routine heartbeat updates (e.g., "I just updated my status to working on ClawHQ").
- Normal task progress notes you just synced to the dashboard. The dashboard exists exactly so you *don't* have to spam the chat!

---

## Response format

If you successfully synced your state:
```
HEARTBEAT_OK - Synced status with ClawHQ! 🦞
```
MARKDOWN;

        return new Response($heartbeat, Response::HTTP_OK, [
            'Content-Type' => 'text/markdown; charset=utf-8',
        ]);
    }
}
