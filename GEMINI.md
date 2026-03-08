<!-- BEGIN MANAGED AGENT SKILLS BLOCK -->
## Shared Agent Skills

This repo includes shared agent skills from the checked-in Agent Skills source.

When a task matches one of the skill triggers below, read the corresponding `SKILL.md` instruction file for detailed guidance before proceeding.

### Available Skills

- **figma** — Use the Figma MCP server to fetch design context, screenshots, variables, and assets from Figma, and to translate Figma nodes into production code. Trigger when a task involves Figma URLs, node IDs, design-to-code implementation, or Figma MCP setup and troubleshooting.
  - Instructions: `tools/agent-skills/skills/figma/SKILL.md`
- **figma-implement-design** — Translate Figma nodes into production-ready code with 1:1 visual fidelity using the Figma MCP workflow (design context, screenshots, assets, and project-convention translation). Trigger when the user provides Figma URLs or node IDs, or asks to implement designs or components that must match Figma specs. Requires a working Figma MCP server connection.
  - Instructions: `tools/agent-skills/skills/figma-implement-design/SKILL.md`

### Skill Maintenance

Check whether the shared skills need updating:

```bash
python3 tools/agent-skills/scripts/check_agent_skill_updates.py \
  --repo-root tools/agent-skills \
  --lock-file .codex/agent-skills.lock.json
```

If updates are available, reinstall with:

```bash
python3 tools/agent-skills/scripts/install_agent_skills.py \
  --repo-root tools/agent-skills \
  --lock-file .codex/agent-skills.lock.json
```
<!-- END MANAGED AGENT SKILLS BLOCK -->

