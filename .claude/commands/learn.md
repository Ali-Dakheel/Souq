---
description: >
  Extract reusable patterns and gotchas from this session into CLAUDE.md.
  Run at end of any session where you solved something non-obvious.
  Usage: /learn
allowed-tools: Read, Write, Edit
---

# Extract Session Learnings

Review this session. Extract patterns worth saving for future sessions.

## What to capture
- Gotchas discovered (something that failed unexpectedly and why)
- Tap Payments API quirks found
- RTL/shadcn behavior discovered
- PostgreSQL/Laravel edge cases
- Coolify/Docker deployment gotchas
- A pattern that worked elegantly

## Output format

Append to `CLAUDE.md` section 10 (`## Session learnings`):

```markdown
### [today's date] — [topic]
**Gotcha:** [description]
**Context:** [when this matters]
**Fix:** [what to do]
```

Keep entries to 3-5 lines. Only add things that would genuinely save
time in a future session. Skip things already documented in CLAUDE.md.

Confirm: "Added N learnings to CLAUDE.md."
