"""Настройка Яндекс.Метрики для uzelok64.ru (счётчик 108717789).

Management API: цели URL и JS (action), фильтры шума, базовые параметры счётчика.
Токен ``YANDEX_ACCESS_TOKEN`` в ``scripts/metrica/.env`` с правами
``metrika:read metrika:write``.

  cd scripts/metrica
  python3 uzelok64_counter_setup.py           # план
  python3 uzelok64_counter_setup.py --apply   # запись

Идемпотентно: уже существующие цели и фильтры пропускаются.
"""

from __future__ import annotations

import argparse
import json
import os
import sys
from pathlib import Path
from typing import Any

import requests
from dotenv import load_dotenv

COUNTER_ID = "108717789"
API_COUNTER = f"https://api-metrika.yandex.net/management/v1/counter/{COUNTER_ID}"
API_GOALS = f"{API_COUNTER}/goals"
API_FILTERS = f"{API_COUNTER}/filters"

FILTER_SPECS: list[tuple[str, str, str, str, str]] = [
    ("Локальная разработка: localhost в URL", "url", "contain", "localhost", "exclude"),
    ("Локальная разработка: 127.0.0.1 в URL", "url", "contain", "127.0.0.1", "exclude"),
    ("Черновики Netlify", "url", "contain", "netlify.app", "exclude"),
    ("Черновики Vercel", "url", "contain", "vercel.app", "exclude"),
    ("Черновики Cloudflare Pages", "url", "contain", "pages.dev", "exclude"),
    ("Локальные TLD в URL", "url", "contain", ".local", "exclude"),
    ("Сканеры: wp-admin в URL", "url", "contain", "wp-admin", "exclude"),
    ("Сканеры: phpMyAdmin в URL", "url", "contain", "phpmyadmin", "exclude"),
]

# URL по query page= (сайт на PHP фронт-контроллере).
URL_GOAL_SPECS: list[tuple[str, str]] = [
    ("Интерес: страница контактов", "page=contacts"),
    ("Интерес: мастерская", "page=workshop"),
    ("Интерес: карточка товара", "page=product"),
    ("Каталог: главная с витриной", "page=home"),
]

# JS-цели: ym(counter, 'reachGoal', <идентификатор>) — в API в conditions[].url тот же идентификатор (см. addGoal example).
ACTION_GOAL_SPECS: list[tuple[str, str]] = [
    ("Заявка отправлена (форма)", "order_sent"),
    ("Клик: перейти на Ozon", "ozon_outbound_click"),
]


def load_token() -> str:
    env_path = Path(__file__).resolve().parent / ".env"
    load_dotenv(env_path)
    t = os.getenv("YANDEX_ACCESS_TOKEN", "").strip()
    if not t:
        print("YANDEX_ACCESS_TOKEN пуст в scripts/metrica/.env", file=sys.stderr)
        sys.exit(1)
    return t


def session_headers(token: str) -> dict[str, str]:
    return {"Authorization": f"OAuth {token}", "Accept": "application/json"}


def get_counter(token: str, *, fields: str | None = None) -> dict[str, Any]:
    params: dict[str, str] = {}
    if fields:
        params["field"] = fields
    r = requests.get(API_COUNTER, headers=session_headers(token), params=params, timeout=40)
    if not r.ok:
        raise RuntimeError(f"GET counter {r.status_code}: {r.text[:600]}")
    return (r.json().get("counter") or {})  # type: ignore[return-value]


def put_counter(token: str, partial: dict[str, Any], *, apply: bool) -> None:
    body = {"counter": partial}
    print(f"  PUT counter: {json.dumps(partial, ensure_ascii=False)}")
    if not apply:
        return
    r = requests.put(
        API_COUNTER,
        headers={**session_headers(token), "Content-Type": "application/json"},
        data=json.dumps(body),
        timeout=40,
    )
    if not r.ok:
        raise RuntimeError(f"PUT counter {r.status_code}: {r.text[:800]}")


def list_goals(token: str) -> list[dict[str, Any]]:
    r = requests.get(API_GOALS, headers=session_headers(token), timeout=40)
    if not r.ok:
        raise RuntimeError(f"GET goals {r.status_code}: {r.text[:600]}")
    return list(r.json().get("goals") or [])


def url_goal_exists(goals: list[dict[str, Any]], url_substring: str) -> bool:
    part = url_substring.strip()
    for g in goals:
        if g.get("type") != "url":
            continue
        for c in g.get("conditions") or []:
            if isinstance(c, dict) and part in str(c.get("url", "")):
                return True
    return False


def post_url_goal(token: str, name: str, url_contain: str, *, apply: bool) -> None:
    body = {
        "goal": {
            "name": name,
            "type": "url",
            "conditions": [{"type": "contain", "url": url_contain}],
        }
    }
    print(f"  POST goal (url contain): {name!r} → {url_contain!r}")
    if not apply:
        return
    r = requests.post(
        API_GOALS,
        headers={**session_headers(token), "Content-Type": "application/json"},
        data=json.dumps(body),
        timeout=40,
    )
    if not r.ok:
        raise RuntimeError(f"POST goal {r.status_code}: {r.text[:800]}")


def action_goal_exists(goals: list[dict[str, Any]], reach_id: str) -> bool:
    for g in goals:
        if g.get("type") != "action":
            continue
        for c in g.get("conditions") or []:
            if isinstance(c, dict) and str(c.get("url", "")) == reach_id:
                return True
    return False


def post_action_goal(token: str, title: str, reach_id: str, *, apply: bool) -> None:
    body = {
        "goal": {
            "name": title,
            "type": "action",
            "conditions": [{"type": "exact", "url": reach_id}],
        }
    }
    print(f"  POST goal (action): {title!r} → reachGoal {reach_id!r}")
    if not apply:
        return
    r = requests.post(
        API_GOALS,
        headers={**session_headers(token), "Content-Type": "application/json"},
        data=json.dumps(body),
        timeout=40,
    )
    if not r.ok:
        raise RuntimeError(f"POST action goal {r.status_code}: {r.text[:800]}")


def list_filters(token: str) -> list[dict[str, Any]]:
    r = requests.get(API_FILTERS, headers=session_headers(token), timeout=40)
    if not r.ok:
        raise RuntimeError(f"GET filters {r.status_code}: {r.text[:600]}")
    return list(r.json().get("filters") or [])


def filter_key(f: dict[str, Any]) -> tuple[str, str, str, str]:
    return (
        str(f.get("attr") or ""),
        str(f.get("type") or ""),
        str(f.get("action") or ""),
        str(f.get("value") or ""),
    )


def post_filter(
    token: str,
    *,
    attr: str,
    type_: str,
    value: str,
    action: str,
    apply: bool,
) -> None:
    body = {
        "filter": {
            "attr": attr,
            "type": type_,
            "value": value,
            "action": action,
            "status": "active",
        }
    }
    print(f"  POST filter: {attr} {type_} {action!r} {value!r}")
    if not apply:
        return
    r = requests.post(
        API_FILTERS,
        headers={**session_headers(token), "Content-Type": "application/json"},
        json=body,
        timeout=40,
    )
    if not r.ok:
        raise RuntimeError(f"POST filter {r.status_code}: {r.text[:800]}")


def plan_counter_core(c: dict[str, Any]) -> dict[str, Any]:
    patch: dict[str, Any] = {}
    if c.get("time_zone_name") != "Europe/Saratov":
        patch["time_zone_name"] = "Europe/Saratov"
    if int(c.get("favorite") or 0) != 1:
        patch["favorite"] = 1
    if not c.get("filter_robots"):
        patch["filter_robots"] = 1
    if not c.get("autogoals_enabled"):
        patch["autogoals_enabled"] = True
    if c.get("ecommerce_deduplicator_enabled") is not None and not c.get(
        "ecommerce_deduplicator_enabled"
    ):
        patch["ecommerce_deduplicator_enabled"] = True
    return patch


def main() -> int:
    parser = argparse.ArgumentParser(description="Настройка Метрики uzelok64.ru (108717789)")
    parser.add_argument(
        "--apply",
        action="store_true",
        help="Выполнить запросы к API (без флага — только план)",
    )
    args = parser.parse_args()
    apply = bool(args.apply)
    token = load_token()

    print(f"Счётчик {COUNTER_ID}, apply={apply}\n")

    c = get_counter(token, fields="goals,filters")
    print(
        "Текущее:",
        f"code_status={c.get('code_status')}",
        f"site={c.get('site')}",
        f"time_zone={c.get('time_zone_name')}",
        f"favorite={c.get('favorite')}",
        f"goals={len(c.get('goals') or [])}",
        f"filters={len(c.get('filters') or [])}",
    )

    print("\n1) Параметры счётчика (PUT)")
    patch = plan_counter_core(c)
    if not patch:
        print("  (изменений не требуется)")
    else:
        put_counter(token, patch, apply=apply)

    print("\n2) URL-цели")
    goals = list_goals(token)
    for name, url_part in URL_GOAL_SPECS:
        if url_goal_exists(goals, url_part):
            print(f"  есть: {name}")
            continue
        post_url_goal(token, name, url_part, apply=apply)
        if apply:
            goals = list_goals(token)

    print("\n3) JS-цели (reachGoal)")
    goals = list_goals(token)
    for title, reach_id in ACTION_GOAL_SPECS:
        if action_goal_exists(goals, reach_id):
            print(f"  есть: {title} ({reach_id})")
            continue
        post_action_goal(token, title, reach_id, apply=apply)
        if apply:
            goals = list_goals(token)

    print("\n4) Фильтры трафика (exclude)")
    fkeys = {filter_key(x) for x in list_filters(token)}
    for label, attr, type_, value, action in FILTER_SPECS:
        k = (attr, type_, action, value)
        if k in fkeys:
            print(f"  есть: {label}")
            continue
        post_filter(token, attr=attr, type_=type_, value=value, action=action, apply=apply)
        fkeys.add(k)

    print(
        "\nГотово."
        + (" Изменения применены." if apply else " Dry-run — для записи добавьте --apply.")
    )
    print("Кабинет:", f"https://metrica.yandex.com/overview?id={COUNTER_ID}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
