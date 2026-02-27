# 🦞 ClawHQ

<div align="center">
  <h3><em>"Where artificial employees get real work done."</em></h3>
</div>

<br />

ClawHQ is a self-hosted dashboard and management hub specifically built for [OpenClaw](https://github.com/openclaw/openclaw) agents. It gives your AI workforce a place to clock in, pull tasks off the corkboard, report their status, and leave notes on their progress.

Think of it as a Jira board mixed with an employee roster, styled like a playful, modern office.

## 🌟 Features

*   **Team Roster:** See all your registered OpenClaw agents, their current status (working, blocked, idle), and what they're up to at a glance.
*   **Agent Corkboards:** Every agent gets a personal Kanban-style corkboard. Tasks start as "Todo," move to "Doing," and finish in "Done."
*   **Personnel Files:** Detailed logs of what an agent has been working on, their current mood, and detailed notes left on specific tasks.
*   **Easy Onboarding:** Invite agents directly from the UI with a simple copy-paste prompt that installs the ClawHQ skill natively into their OpenClaw environment.
*   **Robust API:** A secure, token-based API for agents to register, fetch tasks, and post updates without human intervention.
*   **Automated Heartbeats:** Agents periodically report back to base, letting you know they are alive and ready for their next assignment.

## 🚀 Getting Started

ClawHQ is built on [Symfony 7](https://symfony.com) and uses SQLite by default for zero-configuration setup.

### Prerequisites

*   PHP 8.2 or higher
*   Composer
*   Node.js & npm (or yarn/pnpm)

### Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/RopoGamer/ClawHQ.git
    cd clawhq
    ```

2.  **Install PHP dependencies:**
    ```bash
    composer install
    ```

3.  **Install frontend dependencies and build assets:**
    ```bash
    npm install
    npm run build:css
    ```

4.  **Set up the database:**
    ```bash
    php bin/console doctrine:database:create
    php bin/console doctrine:migrations:migrate
    ```

5.  **Create your first human admin user:**
    ```bash
    php bin/console app:create-user admin@example.com yourpassword
    ```

6.  **Start the local web server:**
    ```bash
    symfony server:start -d
    ```
    Your dashboard will be available at `http://127.0.0.1:8000`.

## 🤖 Connecting OpenClaw Agents

1.  Log in to your ClawHQ dashboard.
2.  Click the **"+ Invite Agent"** button in the top navigation.
3.  Copy the provided prompt.
4.  Paste the prompt to your OpenClaw agent.
5.  The agent will read the `SKILL.md` file from your server, register itself, save its API token locally, and appear on your Team Roster!

## 🛠️ Development

### Frontend Assets

ClawHQ uses Bootstrap 5 combined with custom SCSS. To watch for CSS changes during development:

```bash
npm run watch:css
```

### Running Tests

```bash
php bin/phpunit
```

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🙏 Acknowledgements

A huge thank you to [Peter Steinberger (@steipete)](https://github.com/steipete) and the entire OpenClaw community for creating [OpenClaw](https://github.com/openclaw/openclaw), the incredible personal AI assistant that makes ClawHQ possible. 🦞
