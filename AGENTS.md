<!-- BEGIN MANAGED AGENT SKILLS BLOCK -->
## Shared Agent Skills

This repo consumes shared Codex skills from the checked-in Agent Skills source.

Install or refresh the shared skills with:

```bash
python3 tools/agent-skills/scripts/install_agent_skills.py \
  --repo-root tools/agent-skills \
  --lock-file .codex/agent-skills.lock.json
```

Check whether the shared skills changed since the last install with:

```bash
python3 tools/agent-skills/scripts/check_agent_skill_updates.py \
  --repo-root tools/agent-skills \
  --lock-file .codex/agent-skills.lock.json
```

Shared skills currently expected in this repo:
- `figma`
- `figma-implement-design`

Use those skills explicitly when relevant, or write requests that clearly match their trigger descriptions.
<!-- END MANAGED AGENT SKILLS BLOCK -->

