# Same-Origin Livewire Bridge

`trust-medical/same-origin-livewire-bridge` embeds Laravel Livewire 4 components into same-origin HTML that was not rendered by Laravel, such as WordPress pages served from the same scheme, host, and port.

It does not support cross-origin embeds, CORS, iframe mounting, third-party cookies, `SameSite=None`, `credentials: include`, CSRF bypasses, or arbitrary Livewire component names.

## Compatibility

Only CI-verified combinations are supported.

| Package version | PHP | Laravel | Livewire |
| --------------- | --- | ------- | -------- |
| 0.1.x           | 8.4 | 12.x    | 4.3.x    |

Livewire 4 serves JavaScript from hash-based routes such as `/livewire-{hash}/livewire.js` and uses `@livewireScripts` to include its JavaScript and bundled Alpine runtime. This package renders `@livewireScripts` on the Laravel side so the installed Livewire version is used. `@livewireScriptConfig` is for manually bundled Livewire/Alpine builds and is intentionally not used here.

## Installation

```bash
composer require trust-medical/same-origin-livewire-bridge
php artisan vendor:publish --tag=livewire-bridge-config
php artisan vendor:publish --tag=livewire-bridge-assets
```

Reference the published files from WordPress or another same-origin page:

```html
<link rel="stylesheet" href="/vendor/livewire-bridge/livewire-bridge.css" />
<script src="/vendor/livewire-bridge/livewire-bridge.js" defer></script>
```

The package exposes:

- `GET /livewire-bridge/session`
- `POST /livewire-bridge/render`

Both routes are configurable through `config/livewire-bridge.php`.

## Component Registration

Register only the aliases that may be embedded:

```php
return [
    'components' => [
        'reservation' => [
            'component' => App\Livewire\Embedded\ReservationForm::class,
            'allowed_params' => ['placement', 'campaign'],
            'validator' => App\LivewireBridge\ReservationParamsValidator::class,
        ],
    ],
];
```

The browser may send only the public alias, not a PHP class name or arbitrary Livewire internal name. Unknown aliases return 404. Unknown params, invalid JSON, excessive nesting, long strings, duplicate keys, oversized request bodies, and duplicate component keys return 422.

Custom validators implement:

```php
use TrustMedical\SameOriginLivewireBridge\Validation\ComponentParamsValidator;

final class ReservationParamsValidator implements ComponentParamsValidator
{
    public function validate(string $componentAlias, array $params): array
    {
        // Validate and normalize.
        return $params;
    }
}
```

## Markup API

```html
<livewire-bridge
  data-component="reservation"
  data-key="reservation-price-page"
  data-params='{"placement":"price-page"}'
></livewire-bridge>
```

`data-key` is optional. If omitted, the client generates a key. This form is also supported:

```html
<div
  data-livewire-bridge
  data-component="reservation"
  data-params='{"placement":"price-page"}'
></div>
```

The default selector is `livewire-bridge, [data-livewire-bridge]`. Legacy Wire Extender markup (`<livewire data-component="...">`) is disabled unless explicitly configured.

## CSRF and Security Flow

1. Client calls `GET /livewire-bridge/session` with `credentials: 'same-origin'`.
2. Laravel `web` middleware starts the session and returns a CSRF token.
3. Client calls `POST /livewire-bridge/render` with `X-CSRF-TOKEN`.
4. Laravel's normal CSRF middleware validates the request.
5. `EnsureSameOrigin` verifies the `Origin` header exactly by scheme, host, and port.
6. The server renders registered Livewire components and returns HTML, component assets, and Livewire script information.
7. The client inserts HTML, loads assets once, loads Livewire once, then calls `Livewire.start()` once.

`APP_URL` may contain a path; Origin comparison uses only scheme, host, and port. Missing `Origin` is rejected by default:

```php
'require_origin_header' => true,
```

CORS response headers are not emitted. Do not add CORS for these routes.

## Lifecycle Events

The client dispatches privacy-safe events on `document`:

- `livewire-bridge:initializing`
- `livewire-bridge:session-ready`
- `livewire-bridge:rendered`
- `livewire-bridge:started`
- `livewire-bridge:error`

Event detail includes aliases, component count, duration, status, and error code only. It never includes params, form values, Livewire snapshots, CSRF tokens, cookies, or session IDs.

Each mount receives:

```html
data-livewire-bridge-state="loading|ready|error"
```

Business events such as `reservation-form:submitted` must be dispatched by the Livewire component itself.

## WordPress Example

See [examples/wordpress-shortcode.php](examples/wordpress-shortcode.php).

```php
wp_enqueue_style(
    'livewire-bridge',
    home_url('/vendor/livewire-bridge/livewire-bridge.css'),
    [],
    '0.1.0'
);

wp_enqueue_script(
    'livewire-bridge',
    home_url('/vendor/livewire-bridge/livewire-bridge.js'),
    [],
    '0.1.0',
    true
);
```

Use WordPress escaping for JSON attributes:

```php
function render_reservation_form_shortcode(array $atts = []): string
{
    $placement = 'price-page';
    $allowed = ['price-page', 'menu-page'];

    if (! in_array($placement, $allowed, true)) {
        return '';
    }

    $params = ['placement' => $placement];

    return sprintf(
        '<livewire-bridge data-component="reservation" data-params="%s" data-clarity-mask="true"></livewire-bridge>',
        esc_attr(wp_json_encode($params))
    );
}
```

## Clarity, GTM, and Privacy

Livewire HTML is inserted into the parent DOM, so analytics tools can observe it like normal page content. For forms containing personal information:

- Add `data-clarity-mask="true"` to the mount or sensitive fields.
- Do not send names, phone numbers, email addresses, free text, params, or Livewire state to analytics events.
- Use only non-personal event values such as form type, placement, success, or failure.
- This package does not log form input values.

GTM example:

```html
<script>
  document.addEventListener('livewire-bridge:started', function (event) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: 'livewire_bridge_started',
      component_count: event.detail.componentCount,
    });
  });
</script>
```

## Alpine and Existing Livewire

By default this package owns the Livewire lifecycle for the page. It fails clearly if:

- `window.Livewire` already exists.
- Livewire is already started.
- `window.Alpine` already exists.
- The initializer is run more than once while a run is active.

Avoid loading Alpine separately in the WordPress theme on pages using this package. Livewire 4 bundles Alpine with its script.

## CSP

Allow same-origin script/style loading and the Livewire hash route:

```http
Content-Security-Policy:
  default-src 'self';
  script-src 'self';
  style-src 'self';
  connect-src 'self';
```

If your Livewire application uses nonce-based CSP, configure Livewire consistently in Laravel so `@livewireScripts` emits the required attributes.

## Bypass Routing Notes

If WordPress receives all requests first and hands off selected first-level URL segments to Laravel, these routes must reach Laravel:

- `/livewire-bridge/session`
- `/livewire-bridge/render`
- `/livewire-{hash}/livewire.js`
- `/livewire-{hash}/update`

If Livewire file uploads are used, Livewire's upload routes must also reach Laravel. The `/livewire-{hash}` segment can change when `APP_KEY` changes, so regenerate bypass route lists after key changes or deploys that alter Livewire routes.

This package does not edit `wp-config.php` or `bypass.php`.

## Troubleshooting

- 419 on render: call `/livewire-bridge/session` first and send `X-CSRF-TOKEN` on render.
- 403 on render: verify `APP_URL`, proxy trusted headers, and the browser `Origin` header.
- Livewire script 404: ensure `/livewire-{hash}/livewire.js` reaches Laravel.
- Alpine conflict: remove theme-provided Alpine on pages using this bridge.
- Asset rejected: set `allow_external_assets=true` only if the component intentionally loads external assets.
- Duplicate startup: include the bridge script once and avoid calling `init()` manually unless auto-start is disabled.

## Quality Commands

```bash
composer test
composer analyse
composer audit
npm run build
npm test
npm run lint
npm audit
```

Run Playwright against a Laravel fixture application:

```bash
E2E_BASE_URL=http://127.0.0.1:8000 npm run test:e2e
```

## License

MIT. See [LICENSE](LICENSE) and [UPSTREAM.md](UPSTREAM.md) for Wire Extender attribution and changes.
