# Changelog

## 0.2.0 - 2026-07-28

- The bridge startup sequence (`GET /livewire-bridge/session` → `POST /livewire-bridge/render`) now recovers from an expired CSRF token/session (HTTP 419) instead of dead-ending on a generic error: it shows a `confirm()` dialog and reloads the page if the visitor accepts, matching Livewire's own session-expired recovery. Shown at most once per page load.
- Added `session_expired_message` and `confirm_on_session_expired` to `config/livewire-bridge.php`, returned to the client via the session endpoint so the message can be localized server-side without editing the host page. Overridable per host page via `data-session-expired-message` / `data-confirm-on-session-expired` on the bridge `<script>` tag.
- After updating, republish the config (`php artisan vendor:publish --tag=livewire-bridge-config --force` or reapply local overrides) to pick up the two new keys, and run `composer update trust-medical/same-origin-livewire-bridge` in consuming applications.

## 0.1.0 - 2026-07-20

- Initial implementation of the same-origin-only Livewire bridge package.
- Added Laravel routes, config publishing, Origin validation, component registry, render/session controllers, TypeScript client, CSS, tests, CI, and documentation.
- Added official support for PHP 8.3 alongside PHP 8.4.
