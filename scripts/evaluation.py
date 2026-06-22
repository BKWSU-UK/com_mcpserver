"""Evaluate the Joomla MCP Server using Claude and read-only tool tasks."""

import argparse
import asyncio
import json
import re
import sys
import time
import traceback
import xml.etree.ElementTree as ET
from pathlib import Path
from typing import Any

SCRIPT_DIR = Path(__file__).resolve().parent
if str(SCRIPT_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPT_DIR))

from anthropic import Anthropic

from connections import create_connection

EVALUATION_PROMPT = """You are an AI assistant with access to Joomla MCP tools.

When given a task, you MUST:
1. Use the available tools to complete the task
2. Provide summary of each step in your approach, wrapped in <summary> tags
3. Provide feedback on the tools provided, wrapped in <feedback> tags
4. Provide your final response, wrapped in <response> tags

Summary Requirements:
- In your <summary> tags, explain the steps you took, which tools you used, and how you arrived at the answer

Feedback Requirements:
- In your <feedback> tags, provide constructive feedback on tool names, parameters, descriptions, and errors

Response Requirements:
- Your response should be concise and directly address what was asked
- Always wrap your final response in <response> tags
- If you cannot solve the task return <response>NOT_FOUND</response>
- For numeric responses, provide just the number
- For True/False questions, answer True or False only
- Your response should go last"""


def parse_evaluation_file(file_path: Path) -> list[dict[str, Any]]:
    tree = ET.parse(file_path)
    evaluations = []
    for qa_pair in tree.getroot().findall(".//qa_pair"):
        question_elem = qa_pair.find("question")
        answer_elem = qa_pair.find("answer")
        if question_elem is not None and answer_elem is not None:
            evaluations.append({
                "question": (question_elem.text or "").strip(),
                "answer": (answer_elem.text or "").strip(),
            })
    return evaluations


def extract_xml_content(text: str, tag: str) -> str | None:
    pattern = rf"<{tag}>(.*?)</{tag}>"
    matches = re.findall(pattern, text, re.DOTALL)
    return matches[-1].strip() if matches else None


async def agent_loop(
    client: Anthropic,
    model: str,
    question: str,
    tools: list[dict[str, Any]],
    connection: Any,
) -> tuple[str | None, dict[str, Any]]:
    messages = [{"role": "user", "content": question}]
    response = await asyncio.to_thread(
        client.messages.create,
        model=model,
        max_tokens=4096,
        system=EVALUATION_PROMPT,
        messages=messages,
        tools=tools,
    )
    messages.append({"role": "assistant", "content": response.content})
    tool_metrics: dict[str, Any] = {}

    while response.stop_reason == "tool_use":
        tool_use = next(block for block in response.content if block.type == "tool_use")
        tool_start_ts = time.time()
        try:
            tool_result = await connection.call_tool(tool_use.name, tool_use.input)
            tool_response = json.dumps(tool_result) if isinstance(tool_result, (dict, list)) else str(tool_result)
        except Exception as exc:
            tool_response = f"Error executing tool {tool_use.name}: {exc}\n{traceback.format_exc()}"

        tool_duration = time.time() - tool_start_ts
        if tool_use.name not in tool_metrics:
            tool_metrics[tool_use.name] = {"count": 0, "durations": []}
        tool_metrics[tool_use.name]["count"] += 1
        tool_metrics[tool_use.name]["durations"].append(tool_duration)

        messages.append({
            "role": "user",
            "content": [{
                "type": "tool_result",
                "tool_use_id": tool_use.id,
                "content": tool_response,
            }],
        })

        response = await asyncio.to_thread(
            client.messages.create,
            model=model,
            max_tokens=4096,
            system=EVALUATION_PROMPT,
            messages=messages,
            tools=tools,
        )
        messages.append({"role": "assistant", "content": response.content})

    response_text = next((block.text for block in response.content if hasattr(block, "text")), None)
    return response_text, tool_metrics


async def run_evaluation(eval_path: Path, connection: Any, model: str) -> str:
    client = Anthropic()
    tools = await connection.list_tools()
    qa_pairs = parse_evaluation_file(eval_path)
    results = []

    for index, qa_pair in enumerate(qa_pairs):
        print(f"Task {index + 1}/{len(qa_pairs)}")
        start_time = time.time()
        response, tool_metrics = await agent_loop(client, model, qa_pair["question"], tools, connection)
        actual = extract_xml_content(response or "", "response")
        results.append({
            "question": qa_pair["question"],
            "expected": qa_pair["answer"],
            "actual": actual,
            "score": int(actual == qa_pair["answer"]) if actual else 0,
            "total_duration": time.time() - start_time,
            "tool_calls": tool_metrics,
            "num_tool_calls": sum(len(metrics["durations"]) for metrics in tool_metrics.values()),
            "summary": extract_xml_content(response or "", "summary"),
            "feedback": extract_xml_content(response or "", "feedback"),
        })

    correct = sum(result["score"] for result in results)
    total = len(results)
    report = [
        "# Joomla MCP Evaluation Report",
        "",
        f"- Accuracy: {correct}/{total} ({(correct / total * 100) if total else 0:.1f}%)",
        f"- Average duration: {sum(r['total_duration'] for r in results) / total if total else 0:.2f}s",
        "",
    ]

    for index, result in enumerate(results, start=1):
        report.extend([
            f"## Task {index}",
            "",
            f"**Question**: {result['question']}",
            f"**Expected**: `{result['expected']}`",
            f"**Actual**: `{result['actual'] or 'N/A'}`",
            f"**Correct**: {'yes' if result['score'] else 'no'}",
            "",
        ])

    return "\n".join(report)


def _extract_bearer_token(headers: list[str] | None) -> str:
    if not headers:
        return ""
    for header in headers:
        if header.lower().startswith("authorization:"):
            value = header.split(":", 1)[1].strip()
            if value.lower().startswith("bearer "):
                return value[7:].strip()
    return ""


async def main() -> None:
    parser = argparse.ArgumentParser(description="Evaluate the Joomla MCP Server")
    parser.add_argument("eval_file", type=Path, help="Path to evaluation XML file")
    parser.add_argument("-t", "--transport", choices=["http", "bridge"], default="http")
    parser.add_argument("-u", "--url", required=True, help="MCP JSON-RPC endpoint URL")
    parser.add_argument("-H", "--header", nargs="+", dest="headers", help="HTTP headers, e.g. 'Authorization: Bearer token'")
    parser.add_argument("-b", "--bridge-script", default="site/mcp-http-bridge.js", help="Bridge script for bridge transport")
    parser.add_argument("-m", "--model", default="claude-sonnet-4-20250514")
    parser.add_argument("-o", "--output", type=Path)
    args = parser.parse_args()

    if not args.eval_file.exists():
        print(f"Evaluation file not found: {args.eval_file}", file=sys.stderr)
        sys.exit(1)

    connection = create_connection(
        transport=args.transport,
        endpoint=args.url,
        bearer_token=_extract_bearer_token(args.headers),
        bridge_script=args.bridge_script if args.transport == "bridge" else "",
    )

    async with connection:
        report = await run_evaluation(args.eval_file, connection, args.model)

    if args.output:
        args.output.write_text(report, encoding="utf-8")
        print(f"Report written to {args.output}")
    else:
        print(report)


if __name__ == "__main__":
    asyncio.run(main())
