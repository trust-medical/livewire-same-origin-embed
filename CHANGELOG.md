# Changelog

## 0.2.2 - 2026-10-05

- Added Laravel 13 support alongside Laravel 12 (`illuminate/*` `^12.0|^13.0`; tested with Testbench 10 and 11, PHP 8.3/8.4, Livewire 4.3).
- The default `render_middleware` and the route fallback now use `PreventRequestForgery` when available (Laravel 13) and `ValidateCsrfToken` otherwise (Laravel 12). A published config naming `ValidateCsrfToken` continues to work. Config keys are unchanged.
- Added `Sec-Fetch-Site` tests (`same-origin`, `same-site`, `cross-site`, `none`, absent, plus `useOriginOnly` / `allowSameSite`). Bridge endpoints are not rejected by Laravel 13's origin verification with default settings. No runtime behaviour changed.
- CI matrix now covers Laravel 12 and 13. Dev constraint for PHPUnit widened to `^11.5|^12.0` (required by Testbench 11).

## 0.2.1 - 2026-07-28

- Pinned the transitive `brace-expansion` dependency to `5.0.8` via npm `overrides` to resolve a high-severity DoS advisory (GHSA-mh99-v99m-4gvg) flagged by `npm audit` in CI. No runtime code changed; `dist/livewire-bridge.js` is unaffected.

## 0.2.0 - 2026-07-28

- The bridge startup sequence (`GET /livewire-bridge/session` → `POST /livewire-bridge/render`) now recovers from an expired CSRF token/session (HTTP 419) instead of dead-ending on a generic error: it shows a `confirm()` dialog and reloads the page if the visitor accepts, matching Livewire's own session-expired recovery. Shown at most once per page load.
- Added `session_expired_message` and `confirm_on_session_expired` to `config/livewire-bridge.php`, returned to the client via the session endpoint so the message can be localized server-side without editing the host page. Overridable per host page via `data-session-expired-message` / `data-confirm-on-session-expired` on the bridge `<script>` tag.
- After updating, republish the config (`php artisan vendor:publish --tag=livewire-bridge-config --force` or reapply local overrides) to pick up the two new keys, and run `composer update trust-medical/same-origin-livewire-bridge` in consuming applications.

## 0.1.0 - 2026-07-20

- Initial implementation of the same-origin-only Livewire bridge package.
- Added Laravel routes, config publishing, Origin validation, component registry, render/session controllers, TypeScript client, CSS, tests, CI, and documentation.
- Added official support for PHP 8.3 alongside PHP 8.4.
