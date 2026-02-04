# AGENTS.local.md Best Practices

## Overview

The `AGENTS.local.md` file allows you to personalize AI agent behavior without modifying shared project rules. This file is gitignored, so your settings won't affect other team members.

## File Structure

```markdown
.config/agents/
├── agents.md              ← Shared rules (don't edit)
├── agents.local.md.example ← This template (don't edit)
└── agents.local.md        ← YOUR settings (gitignored)
```

## Categories

### 1. Environment & Tooling

Configure paths to your specific tools and hardware:

```markdown
## Environment: Tool Paths
- **GIT_CLI_PATH**: "/usr/local/bin/git"
- **SHELL_TYPE**: "zsh"

## Environment: Hardware & OS
- **MEMORY_LIMIT**: "laptop"  # laptop/server
- **OS_TYPE**: "wsl2/windows"  # wsl2/windows/macos/linux

## Environment: Workspace Settings
- **LOCAL_PROJECT_ROOT**: "."
- **TEMP_FILES_DIR**: "./.tmp/agent"
```

**Examples:**
- "I work on a laptop with limited memory, don't suggest running heavy Docker containers unnecessarily"
- "I use WSL2 on Windows, consider this when working with paths and permissions"
- "All network requests should go through local proxy localhost:8080"

### 2. Git Workflow Automation

Make the agent your pair programming partner:

```markdown
## Git Workflow
- **COMMIT_STYLE**: "Conventional Commits (feat/fix/docs)"
- **BRANCH_PREFIX**: "feature/"
- **AUTO_LINT_BEFORE_PUSH**: "true"
- **AUTO_CREATE_BRANCH**: "true"
```

**Examples:**
- "Always suggest commit messages in Conventional Commits format (e.g., feat(auth): add login logic)"
- "Before suggesting git push, remind me to run unit tests with npm test"
- "When I ask to start a new task, suggest branch name as feature/JIRA-123-short-description"

### 3. Code Quality Standards

Your personal coding preferences:

```markdown
## Code Quality
- **CODE_PRINCIPLES**: "SOLID, KISS"
- **TYPE_STRICTNESS**: "no-any"
- **COMMENTS**: "only-complex-functions"
- **HTTP_CLIENT**: "axios"
```

**Examples:**
- "When writing code, always follow SOLID principles and avoid using any in TypeScript"
- "Write JSDoc comments only for complex functions, leave simple ones undocumented"
- "I prefer using Axios over fetch for all HTTP requests"

### 4. Safety & Protection

Protect your codebase from mistakes:

```markdown
## Safety Rules
- **PROTECTED_BRANCHES**: "main,master"
- **DESTRUCTIVE_CONFIRM**: "docker-compose down,git reset --hard"
- **SKIP_FOLDERS**: "temp_logs,*.log"
```

**Examples:**
- "Never suggest making changes directly to main or master branches"
- "When searching the project, ignore temp_logs folder and any .log files"
- "Always ask for confirmation before running docker-compose down or git reset --hard"

### 5. Shadow Mode (Smart Context)

The most powerful feature - letting the agent understand your infrastructure:

```markdown
## Shadow Mode (Smart Context)
- **READ_ENV_FILES**: "true"
- **IGNORE_PATTERNS**: "temp_logs,*.log,*.sqlite"
```

**The Shadow Mode Trick:**

> "If I ask about code, first check .env to see which environment variables are set, but never quote their full values"

This allows the agent to know what services you have configured without you explaining it every time.

**Example usage:**
- User: "How do I configure Redis?"
- Agent (checking .env): "I see you have REDIS_URL configured in your .env. You can use it like this..."

## Example Configs

### Minimal Config (Just Tone & Git)

```markdown
## Personal Preferences
- **Tone**: "Friendly but professional"
- **COMMIT_STYLE**: "Conventional Commits"
```

### Full Power User Config

```markdown
## Environment: Tool Paths
- **GIT_CLI_PATH**: "/usr/bin/git"
- **SHELL_TYPE**: "zsh"

## Environment: Hardware & OS
- **MEMORY_LIMIT**: "laptop"
- **OS_TYPE**: "wsl2/windows"

## Git Workflow
- **COMMIT_STYLE**: "Conventional Commits (feat/fix/docs)"
- **AUTO_LINT_BEFORE_PUSH**: "true"

## Safety Rules
- **PROTECTED_BRANCHES**: "main,master"
- **DESTRUCTIVE_CONFIRM**: "docker-compose down,git reset --hard"

## Personal Preferences
- **Tone**: "Professional, concise"
```

## Quick Checklist

- [ ] Copy `.config/agents/agents.local.md.example` to `.config/agents/agents.local.md`
- [ ] Set correct paths in `Environment: Tool Paths`
- [ ] Configure Git workflow preferences
- [ ] Add safety rules for destructive operations
- [ ] Test with a simple request

## Troubleshooting

### Agent Not Using My Settings

1. Check file path is exactly `.config/agents/agents.local.md`
2. Verify file is not tracked by Git (should be gitignored)
3. Restart your AI editor to reload context
4. Check syntax - use Key-Value format shown in example

### Paths Not Working

Make sure paths are in quotes and use forward slashes:

```markdown
# Correct
- **GIT_CLI_PATH**: "/usr/local/bin/git"

# Incorrect
- GIT_CLI_PATH: /usr/local/bin/git
```

## Best Practices

1. **Review periodically**: Your needs change, update local config accordingly
2. **Share templates**: If you find useful patterns, suggest adding to `.example`
3. **Keep it minimal**: Only add what differs from standard project rules
4. **Test changes**: Make small changes and verify they work
