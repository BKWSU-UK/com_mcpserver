"""Pagination checks for the Joomla MCP evaluation suite."""

from __future__ import annotations

from typing import Any

from joomla_mcp_client import JoomlaHttpClient


def _article_ids(result: dict[str, Any]) -> list[str]:
    ids: list[str] = []
    for row in result.get("data", []):
        if not isinstance(row, dict):
            continue
        attrs = row.get("attributes", row)
        if isinstance(attrs, dict) and attrs.get("id") is not None:
            ids.append(str(attrs["id"]))
        elif row.get("id") is not None:
            ids.append(str(row["id"]))
    return ids


def _meta_total_pages(result: dict[str, Any]) -> int:
    meta = result.get("meta") or {}
    if not isinstance(meta, dict):
        return 1
    value = meta.get("total-pages", meta.get("total_pages", 1))
    try:
        return max(1, int(value))
    except (TypeError, ValueError):
        return 1


def pagination_has_more_matches_meta(client: JoomlaHttpClient, tool: str, arguments: dict[str, Any]) -> str:
    result = client.call_tool(tool, arguments)
    if not isinstance(result, dict):
        return "False"
    pagination = result.get("pagination") or {}
    has_more = bool(pagination.get("has_more"))
    return "True" if has_more == (_meta_total_pages(result) > 1) else "False"


def expected_pagination_has_more_matches_meta() -> str:
    return "True"


def articles_page_two_has_items(client: JoomlaHttpClient, limit: int, offset: int) -> str:
    first = client.call_tool("search_articles", {})
    if not isinstance(first, dict) or _meta_total_pages(first) <= 1:
        return "False"
    result = client.call_tool("search_articles", {"limit": limit, "offset": offset})
    if not isinstance(result, dict):
        return "False"
    count = int((result.get("pagination") or {}).get("count", 0))
    return "True" if count > 0 else "False"


def expected_articles_page_two_has_items(client: JoomlaHttpClient) -> str:
    first = client.call_tool("search_articles", {})
    if not isinstance(first, dict):
        return "False"
    return "True" if _meta_total_pages(first) > 1 else "False"


def search_articles_page_overlap(client: JoomlaHttpClient, limit: int, first_offset: int, second_offset: int) -> str:
    first = client.call_tool("search_articles", {"limit": limit, "offset": first_offset})
    second = client.call_tool("search_articles", {"limit": limit, "offset": second_offset})
    first_ids = set(_article_ids(first if isinstance(first, dict) else {}))
    second_ids = set(_article_ids(second if isinstance(second, dict) else {}))
    overlap = bool(first_ids & second_ids)
    return "True" if overlap else "False"


def expected_search_articles_page_overlap(client: JoomlaHttpClient) -> str:
    first = client.call_tool("search_articles", {})
    if not isinstance(first, dict):
        return "False"
    return "False" if _meta_total_pages(first) > 1 else "False"


def walk_exceeds_first_api_page(client: JoomlaHttpClient, limit: int) -> str:
    first = client.call_tool("search_articles", {})
    if not isinstance(first, dict) or _meta_total_pages(first) <= 1:
        return "False"
    offset = 0
    seen: set[str] = set()
    for _ in range(100):
        result = client.call_tool("search_articles", {"limit": limit, "offset": offset})
        if not isinstance(result, dict):
            break
        seen.update(_article_ids(result))
        pagination = result.get("pagination") or {}
        if not pagination.get("has_more"):
            break
        next_offset = pagination.get("next_offset")
        if next_offset is None:
            break
        offset = int(next_offset)
    first_page_size = len(_article_ids(first))
    return "True" if len(seen) > first_page_size else "False"


def expected_walk_exceeds_first_api_page(client: JoomlaHttpClient) -> str:
    first = client.call_tool("search_articles", {})
    if not isinstance(first, dict):
        return "False"
    return "True" if _meta_total_pages(first) > 1 else "False"
