"""OAuth helper for Yandex Metrica and (separately) Yandex Webmaster.

Metrica:
  1) Fill YANDEX_CLIENT_ID and YANDEX_CLIENT_SECRET in .env
  2) Run: python3 auth_setup.py url
  3) Open printed URL, authorize app, copy verification_code
  4) Run: python3 auth_setup.py token <verification_code>

Webmaster (second OAuth app — own client_id / secret in .env):
  1) Fill webmaster.YANDEX_CLIENT_ID and webmaster.YANDEX_CLIENT_SECRET
     (or WEBMASTER_YANDEX_CLIENT_ID / WEBMASTER_YANDEX_CLIENT_SECRET)
  2) Run: python3 auth_setup.py url --webmaster
  3) Run: python3 auth_setup.py token --webmaster <verification_code>
"""

from __future__ import annotations

import argparse
import sys
from urllib.parse import urlencode

import requests
from dotenv import find_dotenv, load_dotenv, set_key
import os

OAUTH_AUTHORIZE_URL = "https://oauth.yandex.ru/authorize"
OAUTH_TOKEN_URL = "https://oauth.yandex.ru/token"


def build_authorization_url(client_id: str, scope: str | None = "metrika:read") -> str:
    """Build OAuth URL for obtaining verification_code.

    For creating goals via Management API, use ``metrika:read metrika:write``
    (set ``YANDEX_OAUTH_SCOPE`` in ``.env`` or pass ``--scope`` to ``url``).

    Pass ``scope=None`` or ``""`` to omit ``scope=`` (typical for Webmaster-only OAuth apps).
    """
    params: dict[str, str] = {
        "response_type": "code",
        "client_id": client_id,
    }
    if scope:
        params["scope"] = scope
    return f"{OAUTH_AUTHORIZE_URL}?{urlencode(params)}"


def exchange_code_for_token(
    client_id: str,
    client_secret: str,
    verification_code: str,
) -> str:
    """Exchange verification_code for permanent access token."""
    payload = {
        "grant_type": "authorization_code",
        "code": verification_code,
        "client_id": client_id,
        "client_secret": client_secret,
    }
    try:
        response = requests.post(OAUTH_TOKEN_URL, data=payload, timeout=20)
    except requests.RequestException as exc:
        raise RuntimeError(f"Network error while requesting token: {exc}") from exc

    if response.status_code != 200:
        try:
            details = response.json()
        except ValueError:
            details = response.text
        raise RuntimeError(
            f"Token request failed ({response.status_code}): {details}"
        )

    data = response.json()
    token = data.get("access_token")
    if not token:
        raise RuntimeError(f"No access_token in response: {data}")
    return token


def save_access_token_to_env(access_token: str, env_path: str) -> None:
    """Persist access token into .env file."""
    set_key(env_path, "YANDEX_ACCESS_TOKEN", access_token)


def save_webmaster_access_token_to_env(access_token: str, env_path: str) -> None:
    """Persist Webmaster OAuth token (same app as api.yandex.ru/webmaster flow)."""
    set_key(env_path, "webmaster.YANDEX_ACCESS_TOKEN", access_token)


def _webmaster_client_id() -> str:
    return (
        os.getenv("WEBMASTER_YANDEX_CLIENT_ID", "").strip()
        or os.getenv("webmaster.YANDEX_CLIENT_ID", "").strip()
    )


def _webmaster_client_secret() -> str:
    return (
        os.getenv("WEBMASTER_YANDEX_CLIENT_SECRET", "").strip()
        or os.getenv("webmaster.YANDEX_CLIENT_SECRET", "").strip()
    )


def main() -> int:
    load_dotenv()
    env_path = find_dotenv(".env", usecwd=True) or ".env"

    parser = argparse.ArgumentParser(description="Yandex OAuth setup helper")
    subparsers = parser.add_subparsers(dest="command", required=True)

    url_parser = subparsers.add_parser("url", help="Print authorization URL")
    url_parser.add_argument(
        "--webmaster",
        action="store_true",
        help="Use Webmaster OAuth client (webmaster.YANDEX_* or WEBMASTER_YANDEX_*)",
    )
    url_parser.add_argument(
        "--scope",
        default=None,
        help="OAuth scope(s), space-separated. Metrica default: env YANDEX_OAUTH_SCOPE or metrika:read. Webmaster: omit unless your app needs explicit scopes.",
    )
    token_parser = subparsers.add_parser(
        "token", help="Exchange verification_code and save access token"
    )
    token_parser.add_argument(
        "--webmaster",
        action="store_true",
        help="Use Webmaster OAuth client; save token to webmaster.YANDEX_ACCESS_TOKEN",
    )
    token_parser.add_argument("verification_code", help="Code received from OAuth page")

    args = parser.parse_args()

    if getattr(args, "webmaster", False):
        client_id = _webmaster_client_id()
        client_secret = _webmaster_client_secret()
        env_scope = os.getenv("WEBMASTER_YANDEX_OAUTH_SCOPE", "").strip()
    else:
        client_id = os.getenv("YANDEX_CLIENT_ID", "").strip()
        client_secret = os.getenv("YANDEX_CLIENT_SECRET", "").strip()
        env_scope = os.getenv("YANDEX_OAUTH_SCOPE", "metrika:read").strip()

    if not client_id:
        key = "webmaster.* / WEBMASTER_YANDEX_CLIENT_ID" if args.webmaster else "YANDEX_CLIENT_ID"
        print(f"{key} is empty in .env", file=sys.stderr)
        return 1

    if args.command == "url":
        if args.webmaster:
            scope_arg = args.scope if args.scope is not None else env_scope
            # Webmaster partner apps often have rights bound to the app, not scope= in URL
            scope_effective: str | None = scope_arg if scope_arg else None
        else:
            scope_effective = (
                args.scope if args.scope is not None else env_scope or "metrika:read"
            )
        auth_url = build_authorization_url(client_id=client_id, scope=scope_effective)
        print("Open this URL in browser and authorize app:")
        print(auth_url)
        print(f"(scope: {scope_effective!r})")
        return 0

    if not client_secret:
        key = "webmaster.* secret" if args.webmaster else "YANDEX_CLIENT_SECRET"
        print(f"{key} is empty in .env", file=sys.stderr)
        return 1

    try:
        token = exchange_code_for_token(
            client_id=client_id,
            client_secret=client_secret,
            verification_code=args.verification_code,
        )
        if args.webmaster:
            save_webmaster_access_token_to_env(token, env_path)
            print("Access token saved to .env (webmaster.YANDEX_ACCESS_TOKEN).")
        else:
            save_access_token_to_env(token, env_path)
            print("Access token saved to .env (YANDEX_ACCESS_TOKEN).")
    except RuntimeError as exc:
        print(str(exc), file=sys.stderr)
        return 1

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
