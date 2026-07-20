# Upstream Attribution

This package is based on ideas and selected implementation patterns from Wire Extender.

- Upstream repository: https://github.com/wire-elements/wire-extender
- Branch inspected: `2.x`
- Commit inspected: `c91a0e5` (`[2.x] Remove Livewire v3 support (#53)`)
- License: MIT
- Original copyright: Copyright (c) 2024 Philo Hermans

## What Was Kept

- Render Livewire components server-side, return HTML to a JavaScript client, then mount into the host page.
- Let Laravel generate the installed Livewire JavaScript URL and runtime data instead of bundling Livewire.
- Use Livewire's rendered component asset collection for `@assets`.

## What Was Changed

- Same-origin only; no CORS support is added.
- Two-step session initialization preserves Laravel and Livewire CSRF verification.
- No CSRF bypass trait and no `X-Wire-Extender` request marker.
- No `credentials: include`; all bridge fetches use `credentials: 'same-origin'`.
- Components must be explicitly mapped through package config.
- Request validation, Origin validation, rate limiting, lifecycle events, timeout handling, and privacy-safe errors were added.
