# Claude Code Session Guide

How to use Claude Code effectively on this project. Read once, refer back when needed.

---

## Starting every session — say this first

```
Read CLAUDE.md, then tell me:
1. What phase we're on and what's remaining
2. What you recommend working on today
```

This re-orients Claude before it does anything.

---

## Session types

### Build a new feature
```
Read CLAUDE.md.
/plan "feature description"
[review the plan]
implement
```

### Scaffold a new Laravel module
```
Read CLAUDE.md.
/new-module ModuleName
```

### Code review — always a FRESH session
Open a new Claude Code session, then:
```
Read CLAUDE.md.
Use the code-reviewer agent to audit backend/app/Modules/[Name]/
Use the rtl-auditor agent to audit frontend/src/components/[area]/
```
Fresh context = better reviews. Never review in the same session that wrote the code.

### Before any production deploy
```
/pre-deploy
```

### Verify payments integration
```
/tap-verify checkout
/tap-verify webhook
```

### Check current phase progress
```
/phase-check
```

### End of session — save learnings
```
/learn
```

---

## Context window management

Compact at these logical breakpoints (don't wait for auto-compact at 50%):
- After research/exploration, before implementation starts
- After completing one full module, before starting the next
- After debugging a gnarly issue, before continuing feature work

```bash
/compact   # save context, continue in same session
/clear     # fresh start between unrelated tasks
/cost      # check token spend
```

---

## Model routing

| Task | Model |
|---|---|
| Writing migrations, controllers, resources | haiku (subagent default) |
| Standard implementation | sonnet (default) |
| Architecture design, security review, planning | opus |

```bash
/model opus    # for deep reasoning
/model sonnet  # back to normal
```

---

## Parallel development (Phase 2+)

When building two independent modules simultaneously:
```bash
# Terminal 1
claude --worktree feature-catalog --tmux

# Terminal 2
claude --worktree feature-orders --tmux
```

Each gets its own git branch. You review and merge. The worktrees live at
`.claude/worktrees/`. Clean up after merging with `git worktree remove`.

---

## PR continuity

```bash
gh pr create --title "Add Catalog module"
# Session auto-links to PR. Resume later:
claude --from-pr 7
```

---

## MCP management — keep it lean

Each MCP server eats context. Disable what you don't need per session.

When working on frontend only:
```json
// .claude/settings.json
{ "disabledMcpServers": ["github"] }
```

When working on backend only:
```json
{ "disabledMcpServers": ["github", "playwright"] }
```

Always-on: Laravel Boost + Context7.

---

## The simplifier — run after every module

```
/simplify backend/app/Modules/ModuleName/
```

Cleans up code from long sessions. Reduces nesting, removes debug variables,
aligns with Laravel conventions. Never changes behavior.

---

## Common mistakes to avoid

- Starting a session without "Read CLAUDE.md" first
- Letting one session run too long without compacting
- Implementing before running /plan on a new feature
- Reviewing code in the same session that wrote it
- Running /pre-deploy and ignoring blocking issues
- Asking Claude to revise decisions in CLAUDE.md mid-session
  (change them deliberately in the file, not through conversation)
