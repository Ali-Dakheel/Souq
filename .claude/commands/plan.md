---
description: >
  Plan a feature before writing any code. Always run this first.
  Usage: /plan "Add product variant selector"
allowed-tools: Read, Glob, Grep, Agent
---

# Plan: $ARGUMENTS

Before a single line of code, produce a complete implementation plan.

## Step 1: Read context (silently)
- `CLAUDE.md` — architecture rules and current phase
- `backend/app/Modules/` — existing module structure
- `frontend/src/` — existing frontend structure

## Step 2: Check phase gate
Read CLAUDE.md section 8. If the requested feature belongs to a locked phase,
stop and say: "This feature is Phase [N] — Phase [N-1] must complete first."

## Step 3: Use the planner agent
```
Use the planner agent to design: $ARGUMENTS
```

## Step 4: Validate the plan
Before presenting:
- [ ] All BHD amounts use integer fils
- [ ] All new migrations are backward-compatible
- [ ] New events cross-referenced with CLAUDE.md section 5
- [ ] Implementation order: DB → Service → Controller → Resource → Frontend → Tests

## Step 5: Present and confirm
End with:
```
────────────────────────────────────
Ready to implement?
- "implement" to start building
- "revise [aspect]" to change something
- "split" to break into smaller tasks
────────────────────────────────────
```

Do NOT start implementation until the user confirms.
