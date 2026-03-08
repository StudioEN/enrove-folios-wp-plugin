#!/usr/bin/env python3
"""
Groove Insights worker.

This worker accepts a POST from the Groove Insights admin page, runs the weekly
research/generation pipeline, saves local artifacts, and calls the Groove
callback endpoint with a publish-ready payload.

Required environment variables:
  GROOVE_INSIGHTS_WORKER_SECRET
  GEMINI_API_KEY

Optional environment variables:
  GROOVE_INSIGHTS_HOST=127.0.0.1
  GROOVE_INSIGHTS_PORT=8787
  GROOVE_INSIGHTS_OUTPUT_DIR=./scripts/insights-output
  GROOVE_INSIGHTS_PUBLIC_BASE_URL=
  GROOVE_INSIGHTS_MODEL=gemini-2.5-flash
  GROOVE_INSIGHTS_ENABLE_NOTEBOOKLM=0
  GROOVE_INSIGHTS_NOTEBOOKLM_BIN=notebooklm
"""

from __future__ import annotations

import json
import os
import re
import shutil
import subprocess
import threading
import traceback
import urllib.error
import urllib.request
from dataclasses import dataclass
from datetime import datetime
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any

try:
    from google import genai
    from google.genai import types
except Exception:  # pragma: no cover - import error is reported at runtime
    genai = None
    types = None


PROMPT_SCOPE = """
Search for the latest AI news, product releases, and framework updates from the
past 7 days.

Prioritize B2B, SaaS, and developer-focused news over consumer tech.

Limit strictly to:
1. Company research/engineering blogs: OpenAI, Google (DeepMind/Cloud),
   Anthropic, Microsoft, AWS, Meta (FAIR), Mistral, Cohere, Hugging Face,
   Databricks.
2. Frameworks and Tooling: LangChain, LlamaIndex, Vercel AI.
3. arXiv (cs.AI, cs.CL) filtered exclusively for agents, RAG, and evals.
4. Top-tier tech journalism sites reporting on AI infrastructure and SaaS.

Do NOT include speculative rumors, stock prices, generic consumer AI wrappers,
or high-level trend analysis pieces. Focus on concrete product, model, or code
releases.
""".strip()


@dataclass
class WorkerConfig:
    secret: str
    gemini_api_key: str
    host: str
    port: int
    model: str
    output_dir: Path
    public_base_url: str
    notebooklm_enabled: bool
    notebooklm_bin: str

    @classmethod
    def from_env(cls) -> "WorkerConfig":
        return cls(
            secret=os.environ.get("GROOVE_INSIGHTS_WORKER_SECRET", "").strip(),
            gemini_api_key=os.environ.get("GEMINI_API_KEY", "").strip(),
            host=os.environ.get("GROOVE_INSIGHTS_HOST", "127.0.0.1").strip(),
            port=int(os.environ.get("GROOVE_INSIGHTS_PORT", "8787")),
            model=os.environ.get("GROOVE_INSIGHTS_MODEL", "gemini-2.5-flash").strip(),
            output_dir=Path(
                os.environ.get(
                    "GROOVE_INSIGHTS_OUTPUT_DIR",
                    str(Path(__file__).resolve().parent / "insights-output"),
                )
            ),
            public_base_url=os.environ.get("GROOVE_INSIGHTS_PUBLIC_BASE_URL", "").rstrip("/"),
            notebooklm_enabled=os.environ.get("GROOVE_INSIGHTS_ENABLE_NOTEBOOKLM", "0").strip() == "1",
            notebooklm_bin=os.environ.get("GROOVE_INSIGHTS_NOTEBOOKLM_BIN", "notebooklm").strip(),
        )


def extract_json(text: str) -> Any:
    text = (text or "").strip()
    if text.startswith("```json"):
        text = text[7:]
    if text.startswith("```"):
        text = text[3:]
    if text.endswith("```"):
        text = text[:-3]
    text = text.strip()

    first_object = text.find("{")
    first_array = text.find("[")
    start = -1
    end_char = ""

    if first_object != -1 and (first_array == -1 or first_object < first_array):
        start = first_object
        end_char = "}"
    elif first_array != -1:
        start = first_array
        end_char = "]"

    if start != -1:
        end = text.rfind(end_char)
        if end != -1:
            text = text[start : end + 1]

    return json.loads(text)


def json_response(handler: BaseHTTPRequestHandler, status_code: int, payload: dict[str, Any]) -> None:
    body = json.dumps(payload).encode("utf-8")
    handler.send_response(status_code)
    handler.send_header("Content-Type", "application/json")
    handler.send_header("Content-Length", str(len(body)))
    handler.end_headers()
    handler.wfile.write(body)


def log(message: str) -> None:
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] {message}", flush=True)


def request_json(url: str, payload: dict[str, Any], headers: dict[str, str] | None = None) -> tuple[int, str]:
    request = urllib.request.Request(url, method="POST")
    request.add_header("Content-Type", "application/json")
    for key, value in (headers or {}).items():
        request.add_header(key, value)
    request.data = json.dumps(payload).encode("utf-8")

    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            return response.status, response.read().decode("utf-8")
    except urllib.error.HTTPError as exc:
        return exc.code, exc.read().decode("utf-8")
    except Exception as exc:
        return 0, str(exc)


def public_url_for(config: WorkerConfig, run_slug: str, filename: str) -> str:
    if not config.public_base_url:
        return ""
    return f"{config.public_base_url}/{run_slug}/{filename}"


def require_genai() -> None:
    if genai is None or types is None:
        raise RuntimeError(
            "google-genai is not installed in this environment. "
            "Install it before running the worker."
        )


def genai_client(config: WorkerConfig):
    require_genai()
    if not config.gemini_api_key:
        raise RuntimeError("GEMINI_API_KEY is not configured.")
    return genai.Client(api_key=config.gemini_api_key)


def gather_candidates(client: Any, config: WorkerConfig) -> list[dict[str, Any]]:
    prompt = f"""
{PROMPT_SCOPE}

Gather exactly 10 to 15 candidate stories total.

Output strictly as a JSON array where each object has these keys:
- "id"
- "title"
- "url"
- "source"
- "date"
- "short_extract"
""".strip()

    response = client.models.generate_content(
        model=config.model,
        contents=prompt,
        config=types.GenerateContentConfig(
            temperature=0.2,
            tools=[{"google_search": {}}],
        ),
    )
    return extract_json(response.text)


def score_candidates(client: Any, config: WorkerConfig, candidates: list[dict[str, Any]]) -> list[dict[str, Any]]:
    prompt = f"""
Given this JSON array of candidate AI news items:
{json.dumps(candidates, indent=2)}

Evaluate each item strictly on its practical impact for SaaS product teams,
builders, and executives. Use a harsh grading scale. Only allocate 5s for
paradigm-shifting news; use 1-3 for iterative updates.

Return a JSON array with these keys for each item:
- "id"
- "saas_relevance"
- "impact"
- "novelty"
- "credibility"
- "one_sentence_takeaway"
- "why_it_matters_for_saas"
""".strip()

    response = client.models.generate_content(
        model=config.model,
        contents=prompt,
        config=types.GenerateContentConfig(temperature=0.1),
    )
    return extract_json(response.text)


def generate_editorial_package(
    client: Any,
    config: WorkerConfig,
    run_date: str,
    top_stories: list[dict[str, Any]],
) -> dict[str, Any]:
    prompt = f"""
You are preparing a weekly AI executive briefing for senior product, design, and
cross-functional SaaS leaders.

Use this exact Top 5 source set:
{json.dumps(top_stories, indent=2)}

Return strictly valid JSON with this exact shape:
{{
  "weekly_theme": "one sentence",
  "executive_summary": "2 to 3 short paragraphs in plain text",
  "story_details": [
    {{
      "id": "source id",
      "takeaway": "one sentence",
      "impact": "1 to 2 sentences focused on SaaS, design, UX, or operating model implications",
      "summary_html": "<p>2 to 4 paragraphs of HTML</p>",
      "summary_markdown": "2 to 4 paragraphs of markdown"
    }}
  ],
  "podcast_script_markdown": "A professional two-host script under 15 minutes, no phrase 'deep dive'"
}}

Rules:
- Keep the tone analytical, precise, and executive-facing.
- Focus on product UX, design systems, brand implications, and SaaS business model shifts.
- Do not invent sources.
- Do not use buzzwords like "game-changer", "revolutionize", or "cutting-edge".
- The issue title should not appear in the JSON; it will be composed separately.
- Today is {run_date}.
""".strip()

    response = client.models.generate_content(
        model=config.model,
        contents=prompt,
        config=types.GenerateContentConfig(temperature=0.2),
    )
    return extract_json(response.text)


def build_issue_payload(
    run_date: str,
    top_stories: list[dict[str, Any]],
    editorial_package: dict[str, Any],
    markdown_url: str,
    audio_url: str,
) -> dict[str, Any]:
    details = {
        item["id"]: item
        for item in editorial_package.get("story_details", [])
        if isinstance(item, dict) and "id" in item
    }
    weekly_theme = str(editorial_package.get("weekly_theme", "")).strip()
    executive_summary = str(editorial_package.get("executive_summary", "")).strip()

    story_payload = []
    for story in top_stories:
        detail = details.get(story["id"], {})
        story_payload.append(
            {
                "title": story["title"],
                "takeaway": detail.get("takeaway") or story.get("one_sentence_takeaway", ""),
                "impact": detail.get("impact") or story.get("why_it_matters_for_saas", ""),
                "summary_html": detail.get("summary_html", ""),
                "summary": detail.get("summary_markdown", story.get("short_extract", "")),
                "source_name": story.get("source", ""),
                "source_url": story.get("url", ""),
                "date": story.get("date", ""),
            }
        )

    original_sources = [
        {
            "title": story.get("title", ""),
            "publication": story.get("source", ""),
            "date": story.get("date", ""),
            "url": story.get("url", ""),
        }
        for story in top_stories
    ]

    briefing_html_parts = []
    if weekly_theme:
        briefing_html_parts.append(f"<p><strong>Weekly theme:</strong> {weekly_theme}</p>")
    if executive_summary:
        paragraphs = [part.strip() for part in executive_summary.split("\n\n") if part.strip()]
        briefing_html_parts.extend(f"<p>{paragraph}</p>" for paragraph in paragraphs)

    return {
        "status": "completed",
        "issue": {
            "title": f"Weekly AI and SaaS Insights - {run_date}",
            "slug": f"weekly-ai-and-saas-insights-{datetime.now().strftime('%Y-%m-%d')}",
            "summary": executive_summary,
            "summary_html": "".join(briefing_html_parts),
            "briefing_html": "".join(briefing_html_parts),
            "markdown_url": markdown_url,
            "audio_url": audio_url,
        },
        "top_stories": story_payload,
        "original_sources": original_sources,
    }


def compose_markdown(issue_payload: dict[str, Any], podcast_script_markdown: str) -> str:
    issue = issue_payload["issue"]
    lines = [f"# {issue['title']}", ""]
    if issue.get("summary"):
        lines.extend(["## Executive Summary", "", issue["summary"], ""])
    lines.extend(["## Top Stories", ""])

    for index, story in enumerate(issue_payload["top_stories"], start=1):
        lines.append(f"### {index}. {story['title']}")
        lines.append("")
        if story.get("takeaway"):
            lines.append(f"**Takeaway:** {story['takeaway']}")
            lines.append("")
        if story.get("impact"):
            lines.append(f"**Why it matters:** {story['impact']}")
            lines.append("")
        if story.get("summary"):
            lines.append(story["summary"])
            lines.append("")
        if story.get("source_url"):
            label = story.get("source_name") or "Source"
            lines.append(f"[Read on {label}]({story['source_url']})")
            lines.append("")

    lines.extend(["## Original Sources", ""])
    for source in issue_payload["original_sources"]:
        lines.append(
            f"- {source['title']} | {source['publication']} | {source['date']} | {source['url']}"
        )

    if podcast_script_markdown:
        lines.extend(["", "## Podcast Script", "", podcast_script_markdown.strip(), ""])

    return "\n".join(lines).strip() + "\n"


def save_text_file(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")


def notebooklm_available(config: WorkerConfig) -> bool:
    return config.notebooklm_enabled and shutil.which(config.notebooklm_bin) is not None


def run_command(command: list[str], cwd: Path | None = None) -> str:
    completed = subprocess.run(
        command,
        cwd=str(cwd) if cwd else None,
        capture_output=True,
        text=True,
        check=True,
    )
    return completed.stdout.strip()


def extract_notebook_id(output: str) -> str:
    match = re.search(r"Created notebook:\s*([a-zA-Z0-9\-]+)", output)
    if not match:
        raise RuntimeError(f"Unable to parse NotebookLM notebook id from: {output}")
    return match.group(1)


def generate_audio_with_notebooklm(
    config: WorkerConfig,
    run_dir: Path,
    run_slug: str,
    issue_payload: dict[str, Any],
    markdown_path: Path,
) -> tuple[str, str]:
    if not notebooklm_available(config):
        return "", ""

    notebook_name = issue_payload["issue"]["title"]
    notebook_id = extract_notebook_id(
        run_command([config.notebooklm_bin, "create", notebook_name], cwd=run_dir)
    )
    run_command([config.notebooklm_bin, "use", notebook_id], cwd=run_dir)

    for source in issue_payload["original_sources"]:
        url = source.get("url", "")
        if not url:
            continue
        try:
            run_command([config.notebooklm_bin, "source", "add", url], cwd=run_dir)
        except subprocess.CalledProcessError:
            log(f"NotebookLM source add failed for {url}; continuing without it.")

    add_source_output = run_command(
        [config.notebooklm_bin, "source", "add", str(markdown_path), "--json"],
        cwd=run_dir,
    )
    source_payload = extract_json(add_source_output)
    summary_source_id = source_payload.get("source", {}).get("id")

    audio_prompt = (
        "Act as two expert design leaders discussing these latest AI developments "
        "from an executive perspective. Adopt the mindset of an upcoming VP of "
        "Design. Focus on product UX, design operations, brand strategy, SaaS "
        "business models, and immediate actions. Do not use the phrase 'deep dive'."
    )
    generate_output = run_command(
        [
            config.notebooklm_bin,
            "generate",
            "audio",
            audio_prompt,
            "--format",
            "deep-dive",
            "-s",
            summary_source_id,
            "--json",
        ],
        cwd=run_dir,
    )
    task_payload = extract_json(generate_output)
    task_id = task_payload.get("task_id")
    if not task_id:
        raise RuntimeError("NotebookLM did not return an audio task id.")

    run_command(
        [config.notebooklm_bin, "artifact", "wait", task_id, "--timeout", "1200"],
        cwd=run_dir,
    )

    audio_path = run_dir / "podcast.mp3"
    run_command(
        [config.notebooklm_bin, "download", "audio", str(audio_path)],
        cwd=run_dir,
    )

    return str(audio_path), public_url_for(config, run_slug, audio_path.name)


def callback_failure(callback_url: str, callback_secret: str, message: str) -> None:
    payload = {"status": "failed", "error_message": message}
    code, body = request_json(
        callback_url,
        payload,
        headers={"X-Groove-Insights-Secret": callback_secret},
    )
    log(f"Failure callback returned HTTP {code}: {body}")


def process_run(config: WorkerConfig, dispatch_payload: dict[str, Any]) -> None:
    run_id = int(dispatch_payload["run_id"])
    callback_url = str(dispatch_payload["callback_url"])
    callback_secret = str(dispatch_payload["callback_secret"])
    run_date = datetime.now().strftime("%B %d, %Y")
    run_slug = f"run-{run_id}-{datetime.now().strftime('%Y%m%d-%H%M%S')}"
    run_dir = config.output_dir / run_slug
    run_dir.mkdir(parents=True, exist_ok=True)

    try:
        log(f"Starting Groove Insights run {run_id}")
        client = genai_client(config)

        candidates = gather_candidates(client, config)
        if not candidates:
            raise RuntimeError("No candidate stories were returned.")

        scores = score_candidates(client, config, candidates)
        if not scores:
            raise RuntimeError("No candidate scores were returned.")

        score_map = {item["id"]: item for item in scores}
        for candidate in candidates:
            score = score_map.get(candidate["id"], {})
            candidate["saas_relevance"] = int(score.get("saas_relevance", 0))
            candidate["impact"] = int(score.get("impact", 0))
            candidate["novelty"] = int(score.get("novelty", 0))
            candidate["credibility"] = int(score.get("credibility", 0))
            candidate["one_sentence_takeaway"] = score.get("one_sentence_takeaway", "")
            candidate["why_it_matters_for_saas"] = score.get("why_it_matters_for_saas", "")
            candidate["total_score"] = (
                candidate["saas_relevance"]
                + candidate["impact"]
                + candidate["novelty"]
                + candidate["credibility"]
            )

        candidates.sort(key=lambda item: item["total_score"], reverse=True)
        top_stories = candidates[:5]
        save_text_file(run_dir / "candidates.json", json.dumps(candidates, indent=2))

        editorial_package = generate_editorial_package(client, config, run_date, top_stories)
        save_text_file(run_dir / "editorial-package.json", json.dumps(editorial_package, indent=2))

        issue_payload = build_issue_payload(
            run_date=run_date,
            top_stories=top_stories,
            editorial_package=editorial_package,
            markdown_url="",
            audio_url="",
        )

        markdown_content = compose_markdown(
            issue_payload,
            str(editorial_package.get("podcast_script_markdown", "")),
        )
        markdown_path = run_dir / "briefing.md"
        save_text_file(markdown_path, markdown_content)
        markdown_url = public_url_for(config, run_slug, markdown_path.name)

        podcast_script = str(editorial_package.get("podcast_script_markdown", "")).strip()
        if podcast_script:
            save_text_file(run_dir / "podcast-script.md", podcast_script)

        audio_path, audio_url = generate_audio_with_notebooklm(
            config=config,
            run_dir=run_dir,
            run_slug=run_slug,
            issue_payload=issue_payload,
            markdown_path=markdown_path,
        )
        if audio_path:
            log(f"NotebookLM audio saved to {audio_path}")

        issue_payload["issue"]["markdown_url"] = markdown_url
        issue_payload["issue"]["audio_url"] = audio_url
        issue_payload["issue"]["briefing_html"] = issue_payload["issue"].get("briefing_html", "")

        code, body = request_json(
            callback_url,
            issue_payload,
            headers={"X-Groove-Insights-Secret": callback_secret},
        )
        log(f"Completion callback for run {run_id} returned HTTP {code}: {body}")
        if code < 200 or code >= 300:
            raise RuntimeError(f"Callback failed with HTTP {code}: {body}")

    except Exception as exc:
        error_message = f"{exc}\n\n{traceback.format_exc()}"
        log(f"Run {run_id} failed: {error_message}")
        callback_failure(callback_url, callback_secret, str(exc))


class GrooveInsightsWorkerHandler(BaseHTTPRequestHandler):
    server_version = "GrooveInsightsWorker/0.1"

    def do_GET(self) -> None:
        if self.path == "/health":
            json_response(self, 200, {"ok": True})
            return

        json_response(self, 404, {"ok": False, "message": "Not found"})

    def do_POST(self) -> None:
        if self.path != "/run":
            json_response(self, 404, {"ok": False, "message": "Not found"})
            return

        config: WorkerConfig = self.server.config  # type: ignore[attr-defined]
        provided_secret = self.headers.get("X-Groove-Insights-Secret", "").strip()
        if not config.secret or provided_secret != config.secret:
            json_response(self, 401, {"ok": False, "message": "Invalid worker secret"})
            return

        content_length = int(self.headers.get("Content-Length", "0"))
        raw_body = self.rfile.read(content_length).decode("utf-8")

        try:
            payload = json.loads(raw_body)
        except json.JSONDecodeError:
            json_response(self, 400, {"ok": False, "message": "Invalid JSON"})
            return

        required_fields = ["run_id", "callback_url", "callback_secret"]
        missing = [field for field in required_fields if not payload.get(field)]
        if missing:
            json_response(
                self,
                400,
                {"ok": False, "message": f"Missing required fields: {', '.join(missing)}"},
            )
            return

        thread = threading.Thread(
            target=process_run,
            args=(config, payload),
            daemon=True,
        )
        thread.start()

        json_response(
            self,
            202,
            {
                "ok": True,
                "accepted": True,
                "run_id": int(payload["run_id"]),
            },
        )

    def log_message(self, format: str, *args: Any) -> None:
        log(format % args)


def main() -> None:
    config = WorkerConfig.from_env()
    if not config.secret:
        raise RuntimeError("GROOVE_INSIGHTS_WORKER_SECRET is required.")

    config.output_dir.mkdir(parents=True, exist_ok=True)
    server = ThreadingHTTPServer((config.host, config.port), GrooveInsightsWorkerHandler)
    server.config = config  # type: ignore[attr-defined]

    log(f"Groove Insights worker listening on http://{config.host}:{config.port}/run")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        log("Shutting down worker.")
    finally:
        server.server_close()


if __name__ == "__main__":
    main()
