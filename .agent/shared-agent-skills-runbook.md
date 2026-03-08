# Shared Agent Skills Runbook

This repo consumes shared agent skills from the `tools/agent-skills` submodule.

## Current Setup

- Submodule path: `tools/agent-skills`
- Submodule remote: `https://github.com/StudioEN/Base-Agent-Skills.git`
- Codex lock file: `.codex/agent-skills.lock.json`
- Generated instruction files:
  - `AGENTS.md`
  - `GEMINI.md`
  - `CLAUDE.md`
- Update workflow:
  - `.github/workflows/agent-skills-update-check.yml`

## One-Time Conversion Process

This is the process used to replace a machine-local symlink with a portable git submodule.

1. Remove the existing symlink:

```bash
rm tools/agent-skills
```

2. Add the local shared-skills repo as a submodule so the initial clone does not depend on network access:

```bash
git -c protocol.file.allow=always submodule add -b main \
  "/Users/frank/Documents/Dropbox/StudioEN/Project/Groove Folios/Production/Automation/Agent Skills/Base-Agent-Skills" \
  tools/agent-skills
```

3. Rewrite the submodule URL to the portable GitHub remote:

```bash
git submodule set-url tools/agent-skills \
  https://github.com/StudioEN/Base-Agent-Skills.git
```

4. Regenerate the agent instruction files and workflow:

```bash
python3 tools/agent-skills/scripts/bootstrap_consumer_repo.py --repo-root .
```

5. Reinstall Codex skills and refresh the lock file:

```bash
python3 tools/agent-skills/scripts/install_agent_skills.py \
  --repo-root tools/agent-skills \
  --lock-file .codex/agent-skills.lock.json
```

6. Commit the result:

```bash
git add .gitmodules tools/agent-skills AGENTS.md GEMINI.md CLAUDE.md \
  .github/workflows/agent-skills-update-check.yml \
  .codex/agent-skills.lock.json
git commit -m "Add shared agent skills submodule"
```

## Day-To-Day Update Process

Pull the latest shared skills into the submodule:

```bash
git submodule update --init --remote tools/agent-skills
```

Check whether the installed Codex skills are out of date:

```bash
python3 tools/agent-skills/scripts/check_agent_skill_updates.py \
  --repo-root tools/agent-skills \
  --lock-file .codex/agent-skills.lock.json
```

If updates are available, reinstall and refresh generated files:

```bash
python3 tools/agent-skills/scripts/bootstrap_consumer_repo.py --repo-root .
python3 tools/agent-skills/scripts/install_agent_skills.py \
  --repo-root tools/agent-skills \
  --lock-file .codex/agent-skills.lock.json
```

Then commit the updated submodule pointer and any regenerated files.

## Clone Setup On Another Machine

Clone with submodules:

```bash
git clone --recurse-submodules <repo-url>
```

Or, after cloning:

```bash
git submodule update --init --recursive
```

Then install the Codex skills locally:

```bash
python3 tools/agent-skills/scripts/install_agent_skills.py \
  --repo-root tools/agent-skills \
  --lock-file .codex/agent-skills.lock.json
```

Restart Codex after reinstalling skills so the refreshed skill set is loaded.
