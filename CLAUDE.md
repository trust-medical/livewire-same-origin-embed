# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Package Is

`trust-medical/same-origin-livewire-bridge` embeds Laravel Livewire 4 components into same-origin, non-Laravel pages (e.g., WordPress served from the same scheme/host/port). It is deliberately **same-origin only** — no CORS, no iframes, no postMessage. It is derived from `wire-elements/wire-extender` (MIT) with the security model rebuilt; `UPSTREAM.md` documents what was kept vs. changed. Keep that attribution (LICENSE + UPSTREAM.md) intact.

Only CI-verified combinations are supported: 0.1.x = PHP 8.3 and 8.4 / Laravel 12 / Livewire 4.3.

## Commands

PHP:
- `composer test` — PHPUnit (Feature + Unit)
- `vendor/bin/phpunit --filter test_name` or `vendor/bin/phpunit tests/Feature/BridgeRoutesTest.php` — single test / single file
- `composer analyse` — PHPStan (larastan)
- `composer format` — Pint (check without writing: `vendor/bin/pint --test`)

JS/TS:
- `npm run build` — Vite lib build of `resources/js/bridge.ts` → `dist/` (IIFE + sourcemap)
- `npm test` — Vitest (jsdom)
- `npm run lint` — ESLint
- `E2E_BASE_URL=http://127.0.0.1:8000 npm run test:e2e` — Playwright against a separately running Laravel fixture app

CI additionally runs `composer audit`, `npm audit`, and `git diff --exit-code dist/` after building. Because `dist/` is committed and diff-checked, **any change to `resources/js/bridge.ts` must be followed by `npm run build` and committing the regenerated `dist/`**.

## Architecture

Request flow (end to end):
1. A host page includes the published CSS/JS and mount points: `<livewire-bridge data-component="alias" data-params='{...}'>` or `[data-livewire-bridge]` (selector configurable).
2. `resources/js/bridge.ts` on DOMContentLoaded → `GET /livewire-bridge/session` (`credentials: 'same-origin'`) to start the Laravel session and fetch the CSRF token (`SessionController`).
3. `POST /livewire-bridge/render` with `X-CSRF-TOKEN`. Middleware: group `web` (from `middleware` config) plus `render_middleware` = `ValidateCsrfToken` + `throttle:livewire-bridge-render` + `EnsureSameOrigin`.
4. `RenderComponentsRequest` rejects with 422 in order: oversized body → duplicate JSON keys (`JsonDuplicateKeyDetector`, recursion capped at `MAX_DEPTH`) → alias/key regex + component count rules → param shape/depth/string-length checks.
5. `ComponentRegistry` resolves alias → class from the `components` config map, enforcing per-component `allowed_params` (default-deny: no list means every param is rejected) and an optional `validator` implementing `ComponentParamsValidator`.
6. `BridgeManager` renders each component via `Blade::render('@livewire(...)')` and collects `@assets` and `@livewireScripts` output into the JSON response.
7. The client injects the returned HTML, loads assets and the Livewire script once each, then calls `Livewire.start()`. All subsequent Livewire updates bypass this package entirely and hit Livewire's own `/livewire-{hash}/...` routes.

`SameOriginLivewireBridgeServiceProvider` wires everything: config merge, two IP-keyed rate limiters (`livewire-bridge-session` / `livewire-bridge-render`), route registration gated on `enabled`, and publish groups (config; `dist/` JS + `public/` CSS → `public/vendor/livewire-bridge`).

Tests: `tests/Feature` (Orchestra Testbench HTTP tests; fixture components/views in `tests/Fixtures`), `tests/Unit` (pure PHPUnit), `tests/Js` (Vitest), `tests/E2E` (Playwright).

## Security Invariants — Do Not Undo

These are the package's contract; several have regression tests:
- Same-origin only. Never emit CORS headers (`test_cors_headers_are_not_added`), never add an iframe/postMessage transport, never use `credentials: 'include'`.
- CSRF is never bypassed. Upstream Wire Extender's bypass trait was intentionally removed; `tests/Unit/NoCsrfBypassTest.php` guards against reintroducing it.
- `EnsureSameOrigin` fails closed: if neither `livewire-bridge.allowed_origin` nor `app.url` is configured, it returns 403 (`origin_not_configured`). Never derive the expected origin from the incoming request's Host.
- The `session` route intentionally has no `EnsureSameOrigin`: same-origin GET fetches send no Origin header, so adding it would break the flow. Its protection is SOP plus the absence of CORS headers.
- Browsers may only send registered aliases (regex-validated); PHP class names are rejected. Params are allow-listed per component (default-deny).
- Client-facing errors stay generic (stable `error.code` + fixed message); details go to server logs only. Lifecycle events (`livewire-bridge:*`) never include params, form values, Livewire snapshots, tokens, or session IDs.
