# Start Here

Run these commands in order. Nothing else before this is done.

---

## 1. Global setup (once per machine)

```bash
# Install Claude Code
npm install -g @anthropic-ai/claude-code

# Install pnpm (faster npm, required for shadcn)
npm install -g pnpm

# Install everything-claude-code PHP + TypeScript rules globally
git clone https://github.com/affaan-m/everything-claude-code.git /tmp/ecc
cp -r /tmp/ecc/rules/common/* ~/.claude/rules/
cp -r /tmp/ecc/rules/php/* ~/.claude/rules/
cp -r /tmp/ecc/rules/typescript/* ~/.claude/rules/

# Global Claude Code settings (token optimization)
cat > ~/.claude/settings.json << 'EOF'
{
  "model": "sonnet",
  "env": {
    "MAX_THINKING_TOKENS": "10000",
    "CLAUDE_AUTOCOMPACT_PCT_OVERRIDE": "50",
    "CLAUDE_CODE_SUBAGENT_MODEL": "haiku"
  }
}
EOF
```

---

## 2. Frontend scaffold

```bash
# From this directory (bahrain-ecomm/)
pnpm dlx shadcn@latest init -t next

# The wizard will scaffold a Next.js + shadcn project
# When it asks for a project name → type: frontend
# Style: Default
# Base color: Neutral
# CSS variables: Yes

# After it finishes:
cd frontend

# Apply RTL support
pnpm dlx shadcn@latest migrate rtl

# Install shadcn skill (gives Claude project-aware component context)
pnpm dlx skills add shadcn/ui

# Install i18n
pnpm add next-intl

# Install state + data fetching + validation
pnpm add zustand @tanstack/react-query @tanstack/react-query-devtools zod

# Install Framer Motion
pnpm add framer-motion

# Install Context7 MCP (live docs for TanStack, Zod, next-intl, shadcn)
npx ctx7 setup --claude

cd ..
```

---

## 3. Backend scaffold

```bash
# From bahrain-ecomm/
composer create-project laravel/laravel backend

cd backend

# Install Laravel Boost MCP
composer require laravel/boost --dev

# Run interactive installer
php artisan boost:install
# SELECT all that apply:
# ✓ Claude Code
# ✓ Filament
# ✓ Pest
# ✓ Horizon
# ✓ Sanctum
# ✓ Scout
# ✓ Reverb

# Add Boost-generated files to .gitignore (auto-regenerated)
echo -e "\n# Laravel Boost (auto-generated)\n.mcp.json\nCLAUDE.md\nAGENTS.md\nboost.json" >> .gitignore

# Install remaining packages
composer require laravel/sanctum laravel/horizon spatie/laravel-data
composer require pestphp/pest --dev

cd ..
```

---

## 4. Environment setup

```bash
# Copy environment template
cp .env.example .env

# Edit .env and fill in your Tap Payments test keys
# Get them from: dashboard.tap.company → Developers → API Keys
nano .env   # or open in your editor
```

---

## 5. Start Claude Code

```bash
# From bahrain-ecomm/ (the project root)
claude
```

**First prompt:**
```
Read CLAUDE.md, then tell me what phase we're on and what to work on first.
```

---

## 6. Install plugins (inside Claude Code)

Once Claude Code is running, paste these one at a time:

```
/plugin marketplace add affaan-m/everything-claude-code
/plugin install everything-claude-code@everything-claude-code
```

```
/plugin marketplace add laravel/claude-code
/plugin install simplifier@laravel
```

Then browse more at aitmpl.com if you want additional agents.

---

## 7. Docker (when you need the full stack running)

```bash
# Make sure Docker Desktop is running, then:
docker compose up -d

# Check everything is healthy
docker compose ps

# Frontend: http://localhost:3000
# Backend:  http://localhost:8000
# Postgres: localhost:5432
# Redis:    localhost:6379
```

---

## That's it

You're ready. Everything else is in SESSION-GUIDE.md.

The workflow for Phase 1:
1. Open Claude Code: `claude`
2. Say: "Read CLAUDE.md, what do we work on today?"
3. Claude tells you. You build.
4. At the end: `/learn` to save anything useful.
5. `/phase-check` to see progress.
