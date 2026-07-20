import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadBridge() {
  vi.resetModules();
  return await import('../../resources/js/bridge');
}

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

function installScriptLoader(start = vi.fn()): void {
  const originalAppendChild = document.body.appendChild.bind(document.body);

  vi.spyOn(document.body, 'appendChild').mockImplementation((node: Node): Node => {
    const result = originalAppendChild(node);

    if (node instanceof HTMLScriptElement) {
      window.Livewire = {
        start: () => {
          start();
          window.Livewire!.started = true;
        },
      };
      node.onload?.(new Event('load'));
    }

    return result;
  });
}

describe('same-origin livewire bridge client', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    document.head.innerHTML = '';
    document.documentElement.innerHTML = '<head></head><body></body>';
    document.documentElement.setAttribute('data-livewire-bridge-no-autostart', '');
    delete window.Livewire;
    delete window.Alpine;
    delete window.SameOriginLivewireBridge;
    delete window.__sameOriginLivewireBridge;
    vi.restoreAllMocks();
    vi.useRealTimers();
    history.replaceState(null, '', '/price/?utm_source=ignored');
  });

  it('collects mounts, parses params, fetches session/render once, and starts Livewire once', async () => {
    const livewireStart = vi.fn();
    installScriptLoader(livewireStart);

    document.body.innerHTML = `
      <livewire-bridge
        data-component="reservation"
        data-key="reservation-price"
        data-params='{"placement":"price-page"}'
      ></livewire-bridge>
      <div
        data-livewire-bridge
        data-component="questionnaire"
        data-key="questionnaire-price"
        data-params='{"placement":"price-page"}'
      ></div>
    `;

    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse({ csrf_token: 'csrf-token' }))
      .mockResolvedValueOnce(
        jsonResponse({
          components: [
            {
              key: 'reservation-price',
              component: 'reservation',
              html: '<form data-testid="reservation"></form>',
            },
            {
              key: 'questionnaire-price',
              component: 'questionnaire',
              html: '<div data-testid="questionnaire"></div>',
            },
          ],
          assets: [],
          livewireScript:
            '<script src="/livewire-abc/livewire.js" data-csrf="csrf-token" data-update-uri="/livewire-abc/update"></script>',
          config: { allowExternalAssets: false, failIfLivewireAlreadyLoaded: true },
        }),
      );
    vi.stubGlobal('fetch', fetchMock);

    const { initBridge } = await loadBridge();
    await initBridge();
    await initBridge();

    expect(fetchMock).toHaveBeenCalledTimes(2);
    expect(fetchMock.mock.calls[0][0]).toBe('/livewire-bridge/session');
    expect(fetchMock.mock.calls[0][1]).toMatchObject({ credentials: 'same-origin', method: 'GET' });
    expect(fetchMock.mock.calls[1][0]).toBe('/livewire-bridge/render');
    expect(fetchMock.mock.calls[1][1].credentials).toBe('same-origin');
    expect(fetchMock.mock.calls[1][1].headers).not.toHaveProperty('X-Wire-Extender');
    expect(fetchMock.mock.calls[1][1].body).toContain('"placement":"price-page"');
    expect(fetchMock.mock.calls[1][1].body).not.toContain('utm_source');
    expect(document.querySelector('[data-testid="reservation"]')).not.toBeNull();
    expect(document.querySelector('[data-testid="questionnaire"]')).not.toBeNull();
    expect(document.querySelectorAll('script[src*="/livewire-abc/livewire.js"]')).toHaveLength(1);
    expect(livewireStart).toHaveBeenCalledTimes(1);
  });

  it('rejects invalid params json and marks the mount as error', async () => {
    document.body.innerHTML = `
      <livewire-bridge
        data-component="reservation"
        data-params='["not", "object"]'
        data-livewire-bridge-error-message="Unavailable"
      ></livewire-bridge>
    `;
    vi.stubGlobal('fetch', vi.fn());
    const errors: unknown[] = [];
    document.addEventListener('livewire-bridge:error', (event) =>
      errors.push((event as CustomEvent).detail),
    );

    const { initBridge } = await loadBridge();

    await expect(initBridge()).rejects.toMatchObject({ code: 'params_json_invalid' });
    expect(fetch).not.toHaveBeenCalled();
    expect(
      document.querySelector('livewire-bridge')?.getAttribute('data-livewire-bridge-state'),
    ).toBe('error');
    expect(document.querySelector('livewire-bridge')?.textContent).toBe('Unavailable');
    expect(errors).toHaveLength(1);
  });

  it('does not retry 403, 404, 419, or 422 responses', async () => {
    document.body.innerHTML = '<livewire-bridge data-component="reservation"></livewire-bridge>';
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse({ csrf_token: 'csrf-token' }))
      .mockResolvedValueOnce(jsonResponse({ error: { code: 'origin_mismatch' } }, 403));
    vi.stubGlobal('fetch', fetchMock);

    const { initBridge } = await loadBridge();

    await expect(initBridge()).rejects.toMatchObject({ code: 'origin_mismatch', status: 403 });
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });

  it('deduplicates component assets and rejects external assets by default', async () => {
    installScriptLoader();
    document.body.innerHTML =
      '<livewire-bridge data-component="reservation" data-key="reservation-price"></livewire-bridge>';
    vi.stubGlobal(
      'fetch',
      vi
        .fn()
        .mockResolvedValueOnce(jsonResponse({ csrf_token: 'csrf-token' }))
        .mockResolvedValueOnce(
          jsonResponse({
            components: [
              {
                key: document.querySelector('livewire-bridge')?.getAttribute('data-key') ?? 'x',
                component: 'reservation',
                html: '<div></div>',
              },
            ],
            assets: ['<script src="https://cdn.example.test/widget.js"></script>'],
            livewireScript: '<script src="/livewire-abc/livewire.js"></script>',
            config: { allowExternalAssets: false },
          }),
        ),
    );

    const { initBridge } = await loadBridge();

    await expect(initBridge()).rejects.toMatchObject({ code: 'external_asset_rejected' });
  });

  it('aborts timed out requests', async () => {
    vi.useFakeTimers();
    document.body.innerHTML = '<livewire-bridge data-component="reservation"></livewire-bridge>';
    vi.stubGlobal(
      'fetch',
      vi.fn(
        (_url: string, init: RequestInit) =>
          new Promise((_resolve, reject) => {
            init.signal?.addEventListener('abort', () =>
              reject(new DOMException('aborted', 'AbortError')),
            );
          }),
      ),
    );

    const { initBridge } = await loadBridge();
    const promise = initBridge({ sessionTimeoutMs: 10, retryCount: 0 });
    const expectation = expect(promise).rejects.toMatchObject({ code: 'network_timeout' });
    await vi.advanceTimersByTimeAsync(11);

    await expectation;
  });
});
