type BridgeComponentRequest = {
  key: string;
  component: string;
  params: Record<string, unknown>;
};

type BridgeComponentResponse = {
  key: string;
  component: string;
  html: string;
};

type BridgeRenderResponse = {
  components: BridgeComponentResponse[];
  assets: string[];
  livewireScript: string;
  config?: {
    allowExternalAssets?: boolean;
    failIfLivewireAlreadyLoaded?: boolean;
  };
};

type BridgeSessionResponse = {
  csrf_token?: string;
  config?: {
    sessionExpiredMessage?: string;
    confirmOnSessionExpired?: boolean;
  };
};

type BridgeOptions = {
  selector: string;
  sessionUrl: string;
  renderUrl: string;
  errorMessage: string;
  sessionExpiredMessage: string;
  confirmOnSessionExpired: boolean;
  sessionTimeoutMs: number;
  renderTimeoutMs: number;
  retryCount: number;
  retryDelayMs: number;
  allowExternalAssets: boolean;
  failIfLivewireAlreadyLoaded: boolean;
};

type BridgeState = {
  initializing?: Promise<void>;
  started: boolean;
  livewireScript?: Promise<void>;
  loadedAssets: Set<string>;
  sessionExpiredPromptShown?: boolean;
  reloadPage: () => void;
};

declare global {
  interface Window {
    Livewire?: {
      start: () => void | Promise<void>;
      started?: boolean;
    };
    Alpine?: unknown;
    SameOriginLivewireBridge?: {
      init: (options?: Partial<BridgeOptions>) => Promise<void>;
    };
    __sameOriginLivewireBridge?: BridgeState;
  }
}

const DEFAULT_OPTIONS: BridgeOptions = {
  selector: 'livewire-bridge, [data-livewire-bridge]',
  sessionUrl: '/livewire-bridge/session',
  renderUrl: '/livewire-bridge/render',
  errorMessage: 'The embedded form could not be loaded.',
  sessionExpiredMessage: 'This page has expired.\nWould you like to refresh the page?',
  confirmOnSessionExpired: true,
  sessionTimeoutMs: 10000,
  renderTimeoutMs: 15000,
  retryCount: 1,
  retryDelayMs: 250,
  allowExternalAssets: false,
  failIfLivewireAlreadyLoaded: true,
};

class BridgeClientError extends Error {
  constructor(
    readonly code: string,
    message: string,
    readonly status?: number,
  ) {
    super(message);
  }
}

function state(): BridgeState {
  window.__sameOriginLivewireBridge ??= {
    started: false,
    loadedAssets: new Set<string>(),
    reloadPage: () => window.location.reload(),
  };

  return window.__sameOriginLivewireBridge;
}

function readOptions(overrides: Partial<BridgeOptions> = {}): BridgeOptions {
  const script = document.currentScript as HTMLScriptElement | null;
  const dataset = script?.dataset ?? {};

  return {
    ...DEFAULT_OPTIONS,
    selector: dataset.selector ?? DEFAULT_OPTIONS.selector,
    sessionUrl: dataset.sessionUrl ?? DEFAULT_OPTIONS.sessionUrl,
    renderUrl: dataset.renderUrl ?? DEFAULT_OPTIONS.renderUrl,
    errorMessage: dataset.errorMessage ?? DEFAULT_OPTIONS.errorMessage,
    sessionExpiredMessage: dataset.sessionExpiredMessage ?? DEFAULT_OPTIONS.sessionExpiredMessage,
    confirmOnSessionExpired: booleanOption(
      dataset.confirmOnSessionExpired,
      DEFAULT_OPTIONS.confirmOnSessionExpired,
    ),
    sessionTimeoutMs: numberOption(dataset.sessionTimeoutMs, DEFAULT_OPTIONS.sessionTimeoutMs),
    renderTimeoutMs: numberOption(dataset.renderTimeoutMs, DEFAULT_OPTIONS.renderTimeoutMs),
    retryCount: numberOption(dataset.retryCount, DEFAULT_OPTIONS.retryCount),
    retryDelayMs: numberOption(dataset.retryDelayMs, DEFAULT_OPTIONS.retryDelayMs),
    allowExternalAssets: booleanOption(
      dataset.allowExternalAssets,
      DEFAULT_OPTIONS.allowExternalAssets,
    ),
    failIfLivewireAlreadyLoaded: booleanOption(
      dataset.failIfLivewireAlreadyLoaded,
      DEFAULT_OPTIONS.failIfLivewireAlreadyLoaded,
    ),
    ...overrides,
  };
}

function numberOption(value: string | undefined, fallback: number): number {
  if (value === undefined || value === '') {
    return fallback;
  }

  const parsed = Number(value);

  return Number.isFinite(parsed) ? parsed : fallback;
}

function booleanOption(value: string | undefined, fallback: boolean): boolean {
  if (value === undefined || value === '') {
    return fallback;
  }

  return value === 'true' || value === '1';
}

function dispatch(name: string, detail: Record<string, unknown>): void {
  document.dispatchEvent(new CustomEvent(name, { detail }));
}

function safeDetail(
  components: BridgeComponentRequest[],
  startedAt: number,
): Record<string, unknown> {
  return {
    componentAliases: Array.from(new Set(components.map((component) => component.component))),
    componentCount: components.length,
    durationMs: Math.round(performance.now() - startedAt),
  };
}

function collectMounts(
  options: BridgeOptions,
): Array<{ element: HTMLElement; request: BridgeComponentRequest }> {
  const elements = Array.from(document.querySelectorAll<HTMLElement>(options.selector));

  return elements.map((element, index) => {
    const component = element.dataset.component;

    if (!component) {
      throw new BridgeClientError('component_missing', 'A bridge mount is missing data-component.');
    }

    const key = element.dataset.key || generateKey(component, index);
    const params = parseParams(element.dataset.params);

    element.dataset.key = key;
    element.setAttribute('data-livewire-bridge-state', 'loading');

    return {
      element,
      request: { key, component, params },
    };
  });
}

function generateKey(component: string, index: number): string {
  return `bridge-${component}-${index}-${Math.random().toString(36).slice(2, 10)}`;
}

function parseParams(raw: string | undefined): Record<string, unknown> {
  if (raw === undefined || raw === '') {
    return {};
  }

  try {
    const parsed = JSON.parse(raw);

    if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object') {
      throw new BridgeClientError('params_json_invalid', 'data-params must be a JSON object.');
    }

    return parsed as Record<string, unknown>;
  } catch (error) {
    if (error instanceof BridgeClientError) {
      throw error;
    }

    throw new BridgeClientError('params_json_invalid', 'data-params contains invalid JSON.');
  }
}

async function initBridge(overrides: Partial<BridgeOptions> = {}): Promise<void> {
  const currentState = state();

  if (currentState.initializing) {
    return currentState.initializing;
  }

  currentState.initializing = runBridge(readOptions(overrides)).catch((error: unknown) => {
    currentState.initializing = undefined;
    throw error;
  });

  return currentState.initializing;
}

async function runBridge(options: BridgeOptions): Promise<void> {
  const startedAt = performance.now();
  let mounts: Array<{ element: HTMLElement; request: BridgeComponentRequest }> = [];

  try {
    mounts = collectMounts(options);
    const components = mounts.map((mount) => mount.request);

    if (components.length === 0) {
      state().initializing = undefined;
      return;
    }

    dispatch('livewire-bridge:initializing', safeDetail(components, startedAt));
    assertNoRuntimeConflicts(options);

    const session = await requestWithRetry<BridgeSessionResponse>(
      options.sessionUrl,
      {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
        },
      },
      options.sessionTimeoutMs,
      options.retryCount,
      options.retryDelayMs,
      'session_initialization_failed',
    );

    if (!session.csrf_token) {
      throw new BridgeClientError(
        'csrf_token_missing',
        'The session endpoint did not return a CSRF token.',
      );
    }

    const sessionConfig = session.config ?? {};
    options.sessionExpiredMessage =
      sessionConfig.sessionExpiredMessage ?? options.sessionExpiredMessage;
    options.confirmOnSessionExpired =
      sessionConfig.confirmOnSessionExpired ?? options.confirmOnSessionExpired;

    dispatch('livewire-bridge:session-ready', safeDetail(components, startedAt));

    const rendered = await requestWithRetry<BridgeRenderResponse>(
      options.renderUrl,
      {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': session.csrf_token,
        },
        body: JSON.stringify({ components }),
      },
      options.renderTimeoutMs,
      options.retryCount,
      options.retryDelayMs,
      'render_failed',
    );

    const responseConfig = rendered.config ?? {};
    options.allowExternalAssets = responseConfig.allowExternalAssets ?? options.allowExternalAssets;
    options.failIfLivewireAlreadyLoaded =
      responseConfig.failIfLivewireAlreadyLoaded ?? options.failIfLivewireAlreadyLoaded;

    insertComponents(mounts, rendered.components, options);
    dispatch('livewire-bridge:rendered', safeDetail(components, startedAt));

    await loadAssets(rendered.assets ?? [], options);
    await loadLivewireScript(rendered.livewireScript, options);
    await startLivewireOnce();

    for (const mount of mounts) {
      mount.element.setAttribute('data-livewire-bridge-state', 'ready');
    }

    dispatch('livewire-bridge:started', safeDetail(components, startedAt));
  } catch (error) {
    const bridgeError = normalizeError(error);
    console.error(`[same-origin-livewire-bridge] ${bridgeError.code}: ${bridgeError.message}`);
    if (mounts.length > 0) {
      markMountsAsError(mounts, options);
    } else {
      markCandidateElementsAsError(options);
    }
    dispatch('livewire-bridge:error', {
      code: bridgeError.code,
      status: bridgeError.status,
      componentCount: mounts.length,
      durationMs: Math.round(performance.now() - startedAt),
    });

    maybePromptSessionReload(bridgeError, options);

    throw bridgeError;
  }
}

function assertNoRuntimeConflicts(options: BridgeOptions): void {
  if (window.Livewire && options.failIfLivewireAlreadyLoaded) {
    throw new BridgeClientError(
      'livewire_already_loaded',
      'Livewire is already loaded on this page.',
    );
  }

  if (window.Livewire?.started) {
    throw new BridgeClientError(
      'livewire_already_started',
      'Livewire is already started on this page.',
    );
  }

  if (window.Alpine) {
    throw new BridgeClientError('alpine_conflict', 'An existing Alpine runtime was detected.');
  }
}

async function requestWithRetry<T>(
  url: string,
  init: RequestInit,
  timeoutMs: number,
  retryCount: number,
  retryDelayMs: number,
  fallbackCode: string,
): Promise<T> {
  let lastError: unknown;

  for (let attempt = 0; attempt <= retryCount; attempt++) {
    try {
      return await requestJson<T>(url, init, timeoutMs, fallbackCode);
    } catch (error) {
      lastError = error;

      if (!shouldRetry(error) || attempt >= retryCount) {
        throw error;
      }

      await delay(retryDelayMs);
    }
  }

  throw normalizeError(lastError);
}

async function requestJson<T>(
  url: string,
  init: RequestInit,
  timeoutMs: number,
  fallbackCode: string,
): Promise<T> {
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetch(url, {
      ...init,
      signal: controller.signal,
    });

    const json = (await response.json().catch(() => ({}))) as {
      error?: { code?: string; message?: string };
    };

    if (!response.ok) {
      throw new BridgeClientError(
        json.error?.code ?? fallbackCode,
        json.error?.message ?? response.statusText,
        response.status,
      );
    }

    return json as T;
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') {
      throw new BridgeClientError('network_timeout', 'The bridge request timed out.');
    }

    throw error;
  } finally {
    window.clearTimeout(timeout);
  }
}

function shouldRetry(error: unknown): boolean {
  if (error instanceof BridgeClientError && error.status !== undefined) {
    return error.status >= 500;
  }

  return !(error instanceof BridgeClientError) || error.code === 'network_timeout';
}

function insertComponents(
  mounts: Array<{ element: HTMLElement; request: BridgeComponentRequest }>,
  components: BridgeComponentResponse[],
  options: BridgeOptions,
): void {
  const byKey = new Map(components.map((component) => [component.key, component]));

  for (const mount of mounts) {
    const rendered = byKey.get(mount.request.key);

    if (!rendered) {
      throw new BridgeClientError(
        'component_response_missing',
        'A rendered component was missing.',
      );
    }

    mount.element.innerHTML = rendered.html;
    mount.element.dataset.livewireBridgeErrorMessage =
      mount.element.dataset.livewireBridgeErrorMessage ?? options.errorMessage;
  }
}

async function loadAssets(assets: string[], options: BridgeOptions): Promise<void> {
  for (const assetHtml of assets) {
    const template = document.createElement('template');
    template.innerHTML = assetHtml.trim();

    const nodes = Array.from(template.content.children);

    for (const node of nodes) {
      if (node instanceof HTMLScriptElement && node.src) {
        await loadScript(node, options, 'asset_load_failed');
      } else if (node instanceof HTMLLinkElement && node.href) {
        await loadStylesheet(node, options);
      } else if (node instanceof HTMLStyleElement) {
        throw new BridgeClientError(
          'inline_style_asset_rejected',
          'Inline style assets are not loaded by the bridge.',
        );
      }
    }
  }
}

async function loadLivewireScript(scriptHtml: string, options: BridgeOptions): Promise<void> {
  const currentState = state();

  if (currentState.livewireScript) {
    return currentState.livewireScript;
  }

  const template = document.createElement('template');
  template.innerHTML = scriptHtml.trim();
  const script = template.content.querySelector('script[src]');

  if (!(script instanceof HTMLScriptElement)) {
    throw new BridgeClientError(
      'livewire_script_missing',
      'The render response did not include a Livewire script.',
    );
  }

  currentState.livewireScript = loadScript(script, options, 'livewire_script_load_failed');

  return currentState.livewireScript;
}

function loadScript(
  source: HTMLScriptElement,
  options: BridgeOptions,
  errorCode: string,
): Promise<void> {
  const url = absoluteUrl(source.src);
  assertAssetOrigin(url, options);

  const currentState = state();
  const key = `script:${url.href}`;

  if (
    currentState.loadedAssets.has(key) ||
    document.querySelector(`script[src="${cssEscape(url.href)}"]`)
  ) {
    currentState.loadedAssets.add(key);

    return Promise.resolve();
  }

  return new Promise((resolve, reject) => {
    const script = document.createElement('script');

    copyAttributes(source, script);
    script.src = url.href;
    script.onload = () => {
      currentState.loadedAssets.add(key);
      resolve();
    };
    script.onerror = () => reject(new BridgeClientError(errorCode, `Failed to load ${url.href}.`));

    document.body.appendChild(script);
  });
}

function loadStylesheet(source: HTMLLinkElement, options: BridgeOptions): Promise<void> {
  const url = absoluteUrl(source.href);
  assertAssetOrigin(url, options);

  const currentState = state();
  const key = `style:${url.href}`;

  if (
    currentState.loadedAssets.has(key) ||
    document.querySelector(`link[href="${cssEscape(url.href)}"]`)
  ) {
    currentState.loadedAssets.add(key);

    return Promise.resolve();
  }

  return new Promise((resolve, reject) => {
    const link = document.createElement('link');

    copyAttributes(source, link);
    link.href = url.href;
    link.rel = source.rel || 'stylesheet';
    link.onload = () => {
      currentState.loadedAssets.add(key);
      resolve();
    };
    link.onerror = () =>
      reject(new BridgeClientError('asset_load_failed', `Failed to load ${url.href}.`));

    document.head.appendChild(link);
  });
}

function copyAttributes(source: Element, target: Element): void {
  for (const attribute of Array.from(source.attributes)) {
    target.setAttribute(attribute.name, attribute.value);
  }
}

function assertAssetOrigin(url: URL, options: BridgeOptions): void {
  if (!options.allowExternalAssets && url.origin !== window.location.origin) {
    throw new BridgeClientError(
      'external_asset_rejected',
      'An external asset was rejected by the bridge.',
    );
  }
}

async function startLivewireOnce(): Promise<void> {
  const currentState = state();

  if (currentState.started) {
    return;
  }

  if (!window.Livewire) {
    throw new BridgeClientError(
      'livewire_script_load_failed',
      'Livewire did not become available.',
    );
  }

  await window.Livewire.start();
  currentState.started = true;
}

function markMountsAsError(
  mounts: Array<{ element: HTMLElement; request: BridgeComponentRequest }>,
  options: BridgeOptions,
): void {
  for (const mount of mounts) {
    mount.element.setAttribute('data-livewire-bridge-state', 'error');
    mount.element.textContent =
      mount.element.dataset.livewireBridgeErrorMessage ?? options.errorMessage;
  }
}

function maybePromptSessionReload(error: BridgeClientError, options: BridgeOptions): void {
  if (error.status !== 419 || !options.confirmOnSessionExpired) {
    return;
  }

  const bridgeState = state();

  if (bridgeState.sessionExpiredPromptShown) {
    return;
  }

  bridgeState.sessionExpiredPromptShown = true;

  if (window.confirm(options.sessionExpiredMessage)) {
    bridgeState.reloadPage();
  }
}

function markCandidateElementsAsError(options: BridgeOptions): void {
  for (const element of Array.from(document.querySelectorAll<HTMLElement>(options.selector))) {
    element.setAttribute('data-livewire-bridge-state', 'error');
    element.textContent = element.dataset.livewireBridgeErrorMessage ?? options.errorMessage;
  }
}

function normalizeError(error: unknown): BridgeClientError {
  if (error instanceof BridgeClientError) {
    return error;
  }

  if (error instanceof Error) {
    return new BridgeClientError('network_error', error.message);
  }

  return new BridgeClientError('unknown_error', 'The bridge failed.');
}

function absoluteUrl(value: string): URL {
  return new URL(value, window.location.href);
}

function cssEscape(value: string): string {
  return value.replaceAll('\\', '\\\\').replaceAll('"', '\\"');
}

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => window.setTimeout(resolve, ms));
}

window.SameOriginLivewireBridge = {
  init: initBridge,
};

if (document.documentElement.hasAttribute('data-livewire-bridge-no-autostart')) {
  // Test and advanced consumers can opt out and call window.SameOriginLivewireBridge.init() directly.
} else if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    void initBridge();
  });
} else {
  void initBridge();
}

export { initBridge };
