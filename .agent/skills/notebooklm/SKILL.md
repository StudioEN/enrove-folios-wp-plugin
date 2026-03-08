---
name: notebooklm
description: Use Google NotebookLM to do research (add sources, run AI research queries) and generate content (podcasts/audio overviews, videos, quizzes, flashcards, slide decks, infographics, mind maps, reports, data tables). Also supports downloading all generated artifacts locally.
---

# NotebookLM Skill

## Overview

You have access to the `notebooklm` CLI (`notebooklm-py` package). Use it to automate Google NotebookLM for research and content generation tasks.

**Installation (if not already installed):**
```bash
pip install "notebooklm-py[browser]"
playwright install chromium
```

**First-time login (opens browser):**
```bash
notebooklm login
```

## Core Workflow

### 1. Create / Select a Notebook
```bash
# Create a new notebook (returns an ID)
notebooklm create "My Research Topic"

# Set it as active (supports partial ID)
notebooklm use <notebook_id>

# OR check what's currently active
notebooklm status
```

### 2. Add Sources
```bash
# Add a URL
notebooklm source add "https://example.com/article"

# Add a YouTube video (auto-extracts transcript)
notebooklm source add "https://www.youtube.com/watch?v=VIDEO_ID"

# Add a local PDF or file
notebooklm source add "./paper.pdf"

# Run AI web research and auto-import results
notebooklm source add-research "AI safety 2024" --mode deep --import-all

# Non-blocking research (use in agents to avoid timeouts)
notebooklm source add-research "topic" --mode deep --no-wait
# Then wait in a subagent:
notebooklm research wait --import-all --timeout 300
```

### 3. Ask Questions / Chat
```bash
notebooklm ask "What are the key themes?"
notebooklm ask "Summarize the main arguments" --save-as-note
notebooklm summary
```

### 4. Generate Content

All generate commands (except mind-map) are **async by default**. Use `--wait` to block until done, or use `artifact wait <id>` separately. For long-running tasks in agent workflows, avoid `--wait` and inform the user to check back.

```bash
# 🎙️ Podcast / Audio Overview
notebooklm generate audio "Focus on key debates" --format deep-dive --wait
# Formats: deep-dive (default), brief, critique, debate
# Lengths: short, default, long

# 🎬 Video Overview
notebooklm generate video "Explain simply" --style whiteboard --wait
# Styles: auto, classic, whiteboard, kawaii, anime, watercolor, retro-print, heritage, paper-craft

# 📊 Slide Deck
notebooklm generate slide-deck --wait

# ❓ Quiz
notebooklm generate quiz --difficulty hard --wait

# 🃏 Flashcards
notebooklm generate flashcards --quantity more --wait

# 📈 Infographic
notebooklm generate infographic --orientation portrait --wait

# 🗺️ Mind Map (synchronous, instant)
notebooklm generate mind-map

# 📋 Data Table
notebooklm generate data-table "compare key concepts" --wait

# 📄 Report / Study Guide / Blog Post
notebooklm generate report --format study-guide --wait
notebooklm generate report --format briefing-doc --append "Focus on AI trends, keep it under 2 pages" --wait
# Formats: briefing-doc (default), study-guide, blog-post, custom
```

### 5. Download Artifacts Locally
```bash
# Download latest podcast as MP3
notebooklm download audio ./podcast.mp3

# Download latest video
notebooklm download video ./overview.mp4

# Download all audio artifacts
notebooklm download audio --all

# Download slide deck as editable PowerPoint
notebooklm download slide-deck --format pptx ./slides.pptx

# Download quiz as Markdown
notebooklm download quiz --format markdown ./quiz.md

# Download flashcards as JSON
notebooklm download flashcards --format json ./cards.json

# Download mind map as JSON
notebooklm download mind-map ./mindmap.json

# Download data table as CSV
notebooklm download data-table ./data.csv

# Download infographic
notebooklm download infographic ./info.png

# Download report
notebooklm download report ./study-guide.md
```

## Common End-to-End Workflows

### Research → Podcast
```bash
notebooklm create "Climate Change Research"        # note the returned ID
notebooklm use <id>
notebooklm source add "https://en.wikipedia.org/wiki/Climate_change"
notebooklm source add-research "climate change policy 2024" --mode deep --import-all
notebooklm generate audio "Focus on policy solutions" --format debate --wait
notebooklm download audio ./climate-podcast.mp3
```

### YouTube / PDF → Study Materials
```bash
notebooklm create "Exam Prep"
notebooklm use <id>
notebooklm source add "https://www.youtube.com/watch?v=VIDEO_ID"
notebooklm source add "./textbook.pdf"
notebooklm generate quiz --difficulty hard --wait
notebooklm generate flashcards --wait
notebooklm generate report --format study-guide --wait
notebooklm download quiz --format markdown ./quiz.md
notebooklm download flashcards --format json ./cards.json
notebooklm download report ./study-guide.md
```

## Important Notes for Agent Use

1. **Async by default**: Generation commands return immediately. If you need the file right away, use `--wait`. Otherwise, track the `task_id` returned by `--json`.
2. **Partial IDs work**: `notebooklm use abc` matches any notebook starting with "abc".
3. **Language**: Language affects all notebooks globally. Set with `notebooklm language set <code>` (e.g., `en`, `ja`, `zh_Hans`).
4. **Deep research timeouts**: For `--mode deep`, always use `--no-wait` + a follow-up `research wait --import-all` to avoid blocking for 30+ minutes.
5. **Artifact types and file formats**:
   | Type | Extension |
   |------|-----------|
   | audio | .mp3 / .mp4 |
   | video | .mp4 |
   | slide-deck | .pdf or .pptx |
   | infographic | .png |
   | report | .md |
   | mind-map | .json |
   | data-table | .csv |
   | quiz | .json / .md / .html |
   | flashcards | .json / .md / .html |

## Useful Management Commands
```bash
notebooklm list                    # list all notebooks
notebooklm status                  # show active notebook
notebooklm artifact list           # list all generated artifacts
notebooklm artifact list --type audio
notebooklm source list             # list sources in active notebook
notebooklm note list               # list notes
notebooklm history                 # view chat history
notebooklm auth check              # validate login
```
