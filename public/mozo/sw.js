try {
  self["workbox:core:6.4.1"] && _();
} catch (e) {
}
const fallback = (code, ...args) => {
  let msg = code;
  if (args.length > 0) {
    msg += ` :: ${JSON.stringify(args)}`;
  }
  return msg;
};
const messageGenerator = fallback;
class WorkboxError extends Error {
  constructor(errorCode, details) {
    const message = messageGenerator(errorCode, details);
    super(message);
    this.name = errorCode;
    this.details = details;
  }
}
const _cacheNameDetails = {
  googleAnalytics: "googleAnalytics",
  precache: "precache-v2",
  prefix: "workbox",
  runtime: "runtime",
  suffix: typeof registration !== "undefined" ? registration.scope : ""
};
const _createCacheName = (cacheName) => {
  return [_cacheNameDetails.prefix, cacheName, _cacheNameDetails.suffix].filter((value) => value && value.length > 0).join("-");
};
const eachCacheNameDetail = (fn) => {
  for (const key of Object.keys(_cacheNameDetails)) {
    fn(key);
  }
};
const cacheNames = {
  updateDetails: (details) => {
    eachCacheNameDetail((key) => {
      if (typeof details[key] === "string") {
        _cacheNameDetails[key] = details[key];
      }
    });
  },
  getGoogleAnalyticsName: (userCacheName) => {
    return userCacheName || _createCacheName(_cacheNameDetails.googleAnalytics);
  },
  getPrecacheName: (userCacheName) => {
    return userCacheName || _createCacheName(_cacheNameDetails.precache);
  },
  getPrefix: () => {
    return _cacheNameDetails.prefix;
  },
  getRuntimeName: (userCacheName) => {
    return userCacheName || _createCacheName(_cacheNameDetails.runtime);
  },
  getSuffix: () => {
    return _cacheNameDetails.suffix;
  }
};
const logger = null;
function waitUntil(event, asyncFn) {
  const returnPromise = asyncFn();
  event.waitUntil(returnPromise);
  return returnPromise;
}
try {
  self["workbox:precaching:6.4.1"] && _();
} catch (e) {
}
const REVISION_SEARCH_PARAM = "__WB_REVISION__";
function createCacheKey(entry) {
  if (!entry) {
    throw new WorkboxError("add-to-cache-list-unexpected-type", { entry });
  }
  if (typeof entry === "string") {
    const urlObject = new URL(entry, location.href);
    return {
      cacheKey: urlObject.href,
      url: urlObject.href
    };
  }
  const { revision, url } = entry;
  if (!url) {
    throw new WorkboxError("add-to-cache-list-unexpected-type", { entry });
  }
  if (!revision) {
    const urlObject = new URL(url, location.href);
    return {
      cacheKey: urlObject.href,
      url: urlObject.href
    };
  }
  const cacheKeyURL = new URL(url, location.href);
  const originalURL = new URL(url, location.href);
  cacheKeyURL.searchParams.set(REVISION_SEARCH_PARAM, revision);
  return {
    cacheKey: cacheKeyURL.href,
    url: originalURL.href
  };
}
class PrecacheInstallReportPlugin {
  constructor() {
    this.updatedURLs = [];
    this.notUpdatedURLs = [];
    this.handlerWillStart = async ({ request, state }) => {
      if (state) {
        state.originalRequest = request;
      }
    };
    this.cachedResponseWillBeUsed = async ({ event, state, cachedResponse }) => {
      if (event.type === "install") {
        if (state && state.originalRequest && state.originalRequest instanceof Request) {
          const url = state.originalRequest.url;
          if (cachedResponse) {
            this.notUpdatedURLs.push(url);
          } else {
            this.updatedURLs.push(url);
          }
        }
      }
      return cachedResponse;
    };
  }
}
class PrecacheCacheKeyPlugin {
  constructor({ precacheController: precacheController2 }) {
    this.cacheKeyWillBeUsed = async ({ request, params }) => {
      const cacheKey = (params === null || params === void 0 ? void 0 : params.cacheKey) || this._precacheController.getCacheKeyForURL(request.url);
      return cacheKey ? new Request(cacheKey, { headers: request.headers }) : request;
    };
    this._precacheController = precacheController2;
  }
}
let supportStatus;
function canConstructResponseFromBodyStream() {
  if (supportStatus === void 0) {
    const testResponse = new Response("");
    if ("body" in testResponse) {
      try {
        new Response(testResponse.body);
        supportStatus = true;
      } catch (error) {
        supportStatus = false;
      }
    }
    supportStatus = false;
  }
  return supportStatus;
}
async function copyResponse(response, modifier) {
  let origin = null;
  if (response.url) {
    const responseURL = new URL(response.url);
    origin = responseURL.origin;
  }
  if (origin !== self.location.origin) {
    throw new WorkboxError("cross-origin-copy-response", { origin });
  }
  const clonedResponse = response.clone();
  const responseInit = {
    headers: new Headers(clonedResponse.headers),
    status: clonedResponse.status,
    statusText: clonedResponse.statusText
  };
  const modifiedResponseInit = modifier ? modifier(responseInit) : responseInit;
  const body = canConstructResponseFromBodyStream() ? clonedResponse.body : await clonedResponse.blob();
  return new Response(body, modifiedResponseInit);
}
const getFriendlyURL = (url) => {
  const urlObj = new URL(String(url), location.href);
  return urlObj.href.replace(new RegExp(`^${location.origin}`), "");
};
function stripParams(fullURL, ignoreParams) {
  const strippedURL = new URL(fullURL);
  for (const param of ignoreParams) {
    strippedURL.searchParams.delete(param);
  }
  return strippedURL.href;
}
async function cacheMatchIgnoreParams(cache, request, ignoreParams, matchOptions) {
  const strippedRequestURL = stripParams(request.url, ignoreParams);
  if (request.url === strippedRequestURL) {
    return cache.match(request, matchOptions);
  }
  const keysOptions = Object.assign(Object.assign({}, matchOptions), { ignoreSearch: true });
  const cacheKeys = await cache.keys(request, keysOptions);
  for (const cacheKey of cacheKeys) {
    const strippedCacheKeyURL = stripParams(cacheKey.url, ignoreParams);
    if (strippedRequestURL === strippedCacheKeyURL) {
      return cache.match(cacheKey, matchOptions);
    }
  }
  return;
}
class Deferred {
  constructor() {
    this.promise = new Promise((resolve, reject) => {
      this.resolve = resolve;
      this.reject = reject;
    });
  }
}
const quotaErrorCallbacks = new Set();
async function executeQuotaErrorCallbacks() {
  for (const callback of quotaErrorCallbacks) {
    await callback();
  }
}
function timeout(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
try {
  self["workbox:strategies:6.4.1"] && _();
} catch (e) {
}
function toRequest(input) {
  return typeof input === "string" ? new Request(input) : input;
}
class StrategyHandler {
  constructor(strategy, options) {
    this._cacheKeys = {};
    Object.assign(this, options);
    this.event = options.event;
    this._strategy = strategy;
    this._handlerDeferred = new Deferred();
    this._extendLifetimePromises = [];
    this._plugins = [...strategy.plugins];
    this._pluginStateMap = new Map();
    for (const plugin of this._plugins) {
      this._pluginStateMap.set(plugin, {});
    }
    this.event.waitUntil(this._handlerDeferred.promise);
  }
  async fetch(input) {
    const { event } = this;
    let request = toRequest(input);
    if (request.mode === "navigate" && event instanceof FetchEvent && event.preloadResponse) {
      const possiblePreloadResponse = await event.preloadResponse;
      if (possiblePreloadResponse) {
        return possiblePreloadResponse;
      }
    }
    const originalRequest = this.hasCallback("fetchDidFail") ? request.clone() : null;
    try {
      for (const cb of this.iterateCallbacks("requestWillFetch")) {
        request = await cb({ request: request.clone(), event });
      }
    } catch (err) {
      if (err instanceof Error) {
        throw new WorkboxError("plugin-error-request-will-fetch", {
          thrownErrorMessage: err.message
        });
      }
    }
    const pluginFilteredRequest = request.clone();
    try {
      let fetchResponse;
      fetchResponse = await fetch(request, request.mode === "navigate" ? void 0 : this._strategy.fetchOptions);
      if (false)
        ;
      for (const callback of this.iterateCallbacks("fetchDidSucceed")) {
        fetchResponse = await callback({
          event,
          request: pluginFilteredRequest,
          response: fetchResponse
        });
      }
      return fetchResponse;
    } catch (error) {
      if (originalRequest) {
        await this.runCallbacks("fetchDidFail", {
          error,
          event,
          originalRequest: originalRequest.clone(),
          request: pluginFilteredRequest.clone()
        });
      }
      throw error;
    }
  }
  async fetchAndCachePut(input) {
    const response = await this.fetch(input);
    const responseClone = response.clone();
    void this.waitUntil(this.cachePut(input, responseClone));
    return response;
  }
  async cacheMatch(key) {
    const request = toRequest(key);
    let cachedResponse;
    const { cacheName, matchOptions } = this._strategy;
    const effectiveRequest = await this.getCacheKey(request, "read");
    const multiMatchOptions = Object.assign(Object.assign({}, matchOptions), { cacheName });
    cachedResponse = await caches.match(effectiveRequest, multiMatchOptions);
    for (const callback of this.iterateCallbacks("cachedResponseWillBeUsed")) {
      cachedResponse = await callback({
        cacheName,
        matchOptions,
        cachedResponse,
        request: effectiveRequest,
        event: this.event
      }) || void 0;
    }
    return cachedResponse;
  }
  async cachePut(key, response) {
    const request = toRequest(key);
    await timeout(0);
    const effectiveRequest = await this.getCacheKey(request, "write");
    if (!response) {
      throw new WorkboxError("cache-put-with-no-response", {
        url: getFriendlyURL(effectiveRequest.url)
      });
    }
    const responseToCache = await this._ensureResponseSafeToCache(response);
    if (!responseToCache) {
      return false;
    }
    const { cacheName, matchOptions } = this._strategy;
    const cache = await self.caches.open(cacheName);
    const hasCacheUpdateCallback = this.hasCallback("cacheDidUpdate");
    const oldResponse = hasCacheUpdateCallback ? await cacheMatchIgnoreParams(cache, effectiveRequest.clone(), ["__WB_REVISION__"], matchOptions) : null;
    try {
      await cache.put(effectiveRequest, hasCacheUpdateCallback ? responseToCache.clone() : responseToCache);
    } catch (error) {
      if (error instanceof Error) {
        if (error.name === "QuotaExceededError") {
          await executeQuotaErrorCallbacks();
        }
        throw error;
      }
    }
    for (const callback of this.iterateCallbacks("cacheDidUpdate")) {
      await callback({
        cacheName,
        oldResponse,
        newResponse: responseToCache.clone(),
        request: effectiveRequest,
        event: this.event
      });
    }
    return true;
  }
  async getCacheKey(request, mode) {
    const key = `${request.url} | ${mode}`;
    if (!this._cacheKeys[key]) {
      let effectiveRequest = request;
      for (const callback of this.iterateCallbacks("cacheKeyWillBeUsed")) {
        effectiveRequest = toRequest(await callback({
          mode,
          request: effectiveRequest,
          event: this.event,
          params: this.params
        }));
      }
      this._cacheKeys[key] = effectiveRequest;
    }
    return this._cacheKeys[key];
  }
  hasCallback(name) {
    for (const plugin of this._strategy.plugins) {
      if (name in plugin) {
        return true;
      }
    }
    return false;
  }
  async runCallbacks(name, param) {
    for (const callback of this.iterateCallbacks(name)) {
      await callback(param);
    }
  }
  *iterateCallbacks(name) {
    for (const plugin of this._strategy.plugins) {
      if (typeof plugin[name] === "function") {
        const state = this._pluginStateMap.get(plugin);
        const statefulCallback = (param) => {
          const statefulParam = Object.assign(Object.assign({}, param), { state });
          return plugin[name](statefulParam);
        };
        yield statefulCallback;
      }
    }
  }
  waitUntil(promise) {
    this._extendLifetimePromises.push(promise);
    return promise;
  }
  async doneWaiting() {
    let promise;
    while (promise = this._extendLifetimePromises.shift()) {
      await promise;
    }
  }
  destroy() {
    this._handlerDeferred.resolve(null);
  }
  async _ensureResponseSafeToCache(response) {
    let responseToCache = response;
    let pluginsUsed = false;
    for (const callback of this.iterateCallbacks("cacheWillUpdate")) {
      responseToCache = await callback({
        request: this.request,
        response: responseToCache,
        event: this.event
      }) || void 0;
      pluginsUsed = true;
      if (!responseToCache) {
        break;
      }
    }
    if (!pluginsUsed) {
      if (responseToCache && responseToCache.status !== 200) {
        responseToCache = void 0;
      }
    }
    return responseToCache;
  }
}
class Strategy {
  constructor(options = {}) {
    this.cacheName = cacheNames.getRuntimeName(options.cacheName);
    this.plugins = options.plugins || [];
    this.fetchOptions = options.fetchOptions;
    this.matchOptions = options.matchOptions;
  }
  handle(options) {
    const [responseDone] = this.handleAll(options);
    return responseDone;
  }
  handleAll(options) {
    if (options instanceof FetchEvent) {
      options = {
        event: options,
        request: options.request
      };
    }
    const event = options.event;
    const request = typeof options.request === "string" ? new Request(options.request) : options.request;
    const params = "params" in options ? options.params : void 0;
    const handler = new StrategyHandler(this, { event, request, params });
    const responseDone = this._getResponse(handler, request, event);
    const handlerDone = this._awaitComplete(responseDone, handler, request, event);
    return [responseDone, handlerDone];
  }
  async _getResponse(handler, request, event) {
    await handler.runCallbacks("handlerWillStart", { event, request });
    let response = void 0;
    try {
      response = await this._handle(request, handler);
      if (!response || response.type === "error") {
        throw new WorkboxError("no-response", { url: request.url });
      }
    } catch (error) {
      if (error instanceof Error) {
        for (const callback of handler.iterateCallbacks("handlerDidError")) {
          response = await callback({ error, event, request });
          if (response) {
            break;
          }
        }
      }
      if (!response) {
        throw error;
      }
    }
    for (const callback of handler.iterateCallbacks("handlerWillRespond")) {
      response = await callback({ event, request, response });
    }
    return response;
  }
  async _awaitComplete(responseDone, handler, request, event) {
    let response;
    let error;
    try {
      response = await responseDone;
    } catch (error2) {
    }
    try {
      await handler.runCallbacks("handlerDidRespond", {
        event,
        request,
        response
      });
      await handler.doneWaiting();
    } catch (waitUntilError) {
      if (waitUntilError instanceof Error) {
        error = waitUntilError;
      }
    }
    await handler.runCallbacks("handlerDidComplete", {
      event,
      request,
      response,
      error
    });
    handler.destroy();
    if (error) {
      throw error;
    }
  }
}
class PrecacheStrategy extends Strategy {
  constructor(options = {}) {
    options.cacheName = cacheNames.getPrecacheName(options.cacheName);
    super(options);
    this._fallbackToNetwork = options.fallbackToNetwork === false ? false : true;
    this.plugins.push(PrecacheStrategy.copyRedirectedCacheableResponsesPlugin);
  }
  async _handle(request, handler) {
    const response = await handler.cacheMatch(request);
    if (response) {
      return response;
    }
    if (handler.event && handler.event.type === "install") {
      return await this._handleInstall(request, handler);
    }
    return await this._handleFetch(request, handler);
  }
  async _handleFetch(request, handler) {
    let response;
    const params = handler.params || {};
    if (this._fallbackToNetwork) {
      const integrityInManifest = params.integrity;
      const integrityInRequest = request.integrity;
      const noIntegrityConflict = !integrityInRequest || integrityInRequest === integrityInManifest;
      response = await handler.fetch(new Request(request, {
        integrity: integrityInRequest || integrityInManifest
      }));
      if (integrityInManifest && noIntegrityConflict) {
        this._useDefaultCacheabilityPluginIfNeeded();
        await handler.cachePut(request, response.clone());
      }
    } else {
      throw new WorkboxError("missing-precache-entry", {
        cacheName: this.cacheName,
        url: request.url
      });
    }
    return response;
  }
  async _handleInstall(request, handler) {
    this._useDefaultCacheabilityPluginIfNeeded();
    const response = await handler.fetch(request);
    const wasCached = await handler.cachePut(request, response.clone());
    if (!wasCached) {
      throw new WorkboxError("bad-precaching-response", {
        url: request.url,
        status: response.status
      });
    }
    return response;
  }
  _useDefaultCacheabilityPluginIfNeeded() {
    let defaultPluginIndex = null;
    let cacheWillUpdatePluginCount = 0;
    for (const [index, plugin] of this.plugins.entries()) {
      if (plugin === PrecacheStrategy.copyRedirectedCacheableResponsesPlugin) {
        continue;
      }
      if (plugin === PrecacheStrategy.defaultPrecacheCacheabilityPlugin) {
        defaultPluginIndex = index;
      }
      if (plugin.cacheWillUpdate) {
        cacheWillUpdatePluginCount++;
      }
    }
    if (cacheWillUpdatePluginCount === 0) {
      this.plugins.push(PrecacheStrategy.defaultPrecacheCacheabilityPlugin);
    } else if (cacheWillUpdatePluginCount > 1 && defaultPluginIndex !== null) {
      this.plugins.splice(defaultPluginIndex, 1);
    }
  }
}
PrecacheStrategy.defaultPrecacheCacheabilityPlugin = {
  async cacheWillUpdate({ response }) {
    if (!response || response.status >= 400) {
      return null;
    }
    return response;
  }
};
PrecacheStrategy.copyRedirectedCacheableResponsesPlugin = {
  async cacheWillUpdate({ response }) {
    return response.redirected ? await copyResponse(response) : response;
  }
};
class PrecacheController {
  constructor({ cacheName, plugins = [], fallbackToNetwork = true } = {}) {
    this._urlsToCacheKeys = new Map();
    this._urlsToCacheModes = new Map();
    this._cacheKeysToIntegrities = new Map();
    this._strategy = new PrecacheStrategy({
      cacheName: cacheNames.getPrecacheName(cacheName),
      plugins: [
        ...plugins,
        new PrecacheCacheKeyPlugin({ precacheController: this })
      ],
      fallbackToNetwork
    });
    this.install = this.install.bind(this);
    this.activate = this.activate.bind(this);
  }
  get strategy() {
    return this._strategy;
  }
  precache(entries) {
    this.addToCacheList(entries);
    if (!this._installAndActiveListenersAdded) {
      self.addEventListener("install", this.install);
      self.addEventListener("activate", this.activate);
      this._installAndActiveListenersAdded = true;
    }
  }
  addToCacheList(entries) {
    const urlsToWarnAbout = [];
    for (const entry of entries) {
      if (typeof entry === "string") {
        urlsToWarnAbout.push(entry);
      } else if (entry && entry.revision === void 0) {
        urlsToWarnAbout.push(entry.url);
      }
      const { cacheKey, url } = createCacheKey(entry);
      const cacheMode = typeof entry !== "string" && entry.revision ? "reload" : "default";
      if (this._urlsToCacheKeys.has(url) && this._urlsToCacheKeys.get(url) !== cacheKey) {
        throw new WorkboxError("add-to-cache-list-conflicting-entries", {
          firstEntry: this._urlsToCacheKeys.get(url),
          secondEntry: cacheKey
        });
      }
      if (typeof entry !== "string" && entry.integrity) {
        if (this._cacheKeysToIntegrities.has(cacheKey) && this._cacheKeysToIntegrities.get(cacheKey) !== entry.integrity) {
          throw new WorkboxError("add-to-cache-list-conflicting-integrities", {
            url
          });
        }
        this._cacheKeysToIntegrities.set(cacheKey, entry.integrity);
      }
      this._urlsToCacheKeys.set(url, cacheKey);
      this._urlsToCacheModes.set(url, cacheMode);
      if (urlsToWarnAbout.length > 0) {
        const warningMessage = `Workbox is precaching URLs without revision info: ${urlsToWarnAbout.join(", ")}
This is generally NOT safe. Learn more at https://bit.ly/wb-precache`;
        {
          console.warn(warningMessage);
        }
      }
    }
  }
  install(event) {
    return waitUntil(event, async () => {
      const installReportPlugin = new PrecacheInstallReportPlugin();
      this.strategy.plugins.push(installReportPlugin);
      for (const [url, cacheKey] of this._urlsToCacheKeys) {
        const integrity = this._cacheKeysToIntegrities.get(cacheKey);
        const cacheMode = this._urlsToCacheModes.get(url);
        const request = new Request(url, {
          integrity,
          cache: cacheMode,
          credentials: "same-origin"
        });
        await Promise.all(this.strategy.handleAll({
          params: { cacheKey },
          request,
          event
        }));
      }
      const { updatedURLs, notUpdatedURLs } = installReportPlugin;
      return { updatedURLs, notUpdatedURLs };
    });
  }
  activate(event) {
    return waitUntil(event, async () => {
      const cache = await self.caches.open(this.strategy.cacheName);
      const currentlyCachedRequests = await cache.keys();
      const expectedCacheKeys = new Set(this._urlsToCacheKeys.values());
      const deletedURLs = [];
      for (const request of currentlyCachedRequests) {
        if (!expectedCacheKeys.has(request.url)) {
          await cache.delete(request);
          deletedURLs.push(request.url);
        }
      }
      return { deletedURLs };
    });
  }
  getURLsToCacheKeys() {
    return this._urlsToCacheKeys;
  }
  getCachedURLs() {
    return [...this._urlsToCacheKeys.keys()];
  }
  getCacheKeyForURL(url) {
    const urlObject = new URL(url, location.href);
    return this._urlsToCacheKeys.get(urlObject.href);
  }
  getIntegrityForCacheKey(cacheKey) {
    return this._cacheKeysToIntegrities.get(cacheKey);
  }
  async matchPrecache(request) {
    const url = request instanceof Request ? request.url : request;
    const cacheKey = this.getCacheKeyForURL(url);
    if (cacheKey) {
      const cache = await self.caches.open(this.strategy.cacheName);
      return cache.match(cacheKey);
    }
    return void 0;
  }
  createHandlerBoundToURL(url) {
    const cacheKey = this.getCacheKeyForURL(url);
    if (!cacheKey) {
      throw new WorkboxError("non-precached-url", { url });
    }
    return (options) => {
      options.request = new Request(url);
      options.params = Object.assign({ cacheKey }, options.params);
      return this.strategy.handle(options);
    };
  }
}
let precacheController;
const getOrCreatePrecacheController = () => {
  if (!precacheController) {
    precacheController = new PrecacheController();
  }
  return precacheController;
};
try {
  self["workbox:routing:6.4.1"] && _();
} catch (e) {
}
const defaultMethod = "GET";
const normalizeHandler = (handler) => {
  if (handler && typeof handler === "object") {
    return handler;
  } else {
    return { handle: handler };
  }
};
class Route {
  constructor(match, handler, method = defaultMethod) {
    this.handler = normalizeHandler(handler);
    this.match = match;
    this.method = method;
  }
  setCatchHandler(handler) {
    this.catchHandler = normalizeHandler(handler);
  }
}
class RegExpRoute extends Route {
  constructor(regExp, handler, method) {
    const match = ({ url }) => {
      const result = regExp.exec(url.href);
      if (!result) {
        return;
      }
      if (url.origin !== location.origin && result.index !== 0) {
        return;
      }
      return result.slice(1);
    };
    super(match, handler, method);
  }
}
class Router {
  constructor() {
    this._routes = new Map();
    this._defaultHandlerMap = new Map();
  }
  get routes() {
    return this._routes;
  }
  addFetchListener() {
    self.addEventListener("fetch", (event) => {
      const { request } = event;
      const responsePromise = this.handleRequest({ request, event });
      if (responsePromise) {
        event.respondWith(responsePromise);
      }
    });
  }
  addCacheListener() {
    self.addEventListener("message", (event) => {
      if (event.data && event.data.type === "CACHE_URLS") {
        const { payload } = event.data;
        const requestPromises = Promise.all(payload.urlsToCache.map((entry) => {
          if (typeof entry === "string") {
            entry = [entry];
          }
          const request = new Request(...entry);
          return this.handleRequest({ request, event });
        }));
        event.waitUntil(requestPromises);
        if (event.ports && event.ports[0]) {
          void requestPromises.then(() => event.ports[0].postMessage(true));
        }
      }
    });
  }
  handleRequest({ request, event }) {
    const url = new URL(request.url, location.href);
    if (!url.protocol.startsWith("http")) {
      return;
    }
    const sameOrigin = url.origin === location.origin;
    const { params, route } = this.findMatchingRoute({
      event,
      request,
      sameOrigin,
      url
    });
    let handler = route && route.handler;
    const method = request.method;
    if (!handler && this._defaultHandlerMap.has(method)) {
      handler = this._defaultHandlerMap.get(method);
    }
    if (!handler) {
      return;
    }
    let responsePromise;
    try {
      responsePromise = handler.handle({ url, request, event, params });
    } catch (err) {
      responsePromise = Promise.reject(err);
    }
    const catchHandler = route && route.catchHandler;
    if (responsePromise instanceof Promise && (this._catchHandler || catchHandler)) {
      responsePromise = responsePromise.catch(async (err) => {
        if (catchHandler) {
          try {
            return await catchHandler.handle({ url, request, event, params });
          } catch (catchErr) {
            if (catchErr instanceof Error) {
              err = catchErr;
            }
          }
        }
        if (this._catchHandler) {
          return this._catchHandler.handle({ url, request, event });
        }
        throw err;
      });
    }
    return responsePromise;
  }
  findMatchingRoute({ url, sameOrigin, request, event }) {
    const routes = this._routes.get(request.method) || [];
    for (const route of routes) {
      let params;
      const matchResult = route.match({ url, sameOrigin, request, event });
      if (matchResult) {
        params = matchResult;
        if (Array.isArray(params) && params.length === 0) {
          params = void 0;
        } else if (matchResult.constructor === Object && Object.keys(matchResult).length === 0) {
          params = void 0;
        } else if (typeof matchResult === "boolean") {
          params = void 0;
        }
        return { route, params };
      }
    }
    return {};
  }
  setDefaultHandler(handler, method = defaultMethod) {
    this._defaultHandlerMap.set(method, normalizeHandler(handler));
  }
  setCatchHandler(handler) {
    this._catchHandler = normalizeHandler(handler);
  }
  registerRoute(route) {
    if (!this._routes.has(route.method)) {
      this._routes.set(route.method, []);
    }
    this._routes.get(route.method).push(route);
  }
  unregisterRoute(route) {
    if (!this._routes.has(route.method)) {
      throw new WorkboxError("unregister-route-but-not-found-with-method", {
        method: route.method
      });
    }
    const routeIndex = this._routes.get(route.method).indexOf(route);
    if (routeIndex > -1) {
      this._routes.get(route.method).splice(routeIndex, 1);
    } else {
      throw new WorkboxError("unregister-route-route-not-registered");
    }
  }
}
let defaultRouter;
const getOrCreateDefaultRouter = () => {
  if (!defaultRouter) {
    defaultRouter = new Router();
    defaultRouter.addFetchListener();
    defaultRouter.addCacheListener();
  }
  return defaultRouter;
};
function registerRoute(capture, handler, method) {
  let route;
  if (typeof capture === "string") {
    const captureUrl = new URL(capture, location.href);
    const matchCallback = ({ url }) => {
      return url.href === captureUrl.href;
    };
    route = new Route(matchCallback, handler, method);
  } else if (capture instanceof RegExp) {
    route = new RegExpRoute(capture, handler, method);
  } else if (typeof capture === "function") {
    route = new Route(capture, handler, method);
  } else if (capture instanceof Route) {
    route = capture;
  } else {
    throw new WorkboxError("unsupported-route-type", {
      moduleName: "workbox-routing",
      funcName: "registerRoute",
      paramName: "capture"
    });
  }
  const defaultRouter2 = getOrCreateDefaultRouter();
  defaultRouter2.registerRoute(route);
  return route;
}
function removeIgnoredSearchParams(urlObject, ignoreURLParametersMatching = []) {
  for (const paramName of [...urlObject.searchParams.keys()]) {
    if (ignoreURLParametersMatching.some((regExp) => regExp.test(paramName))) {
      urlObject.searchParams.delete(paramName);
    }
  }
  return urlObject;
}
function* generateURLVariations(url, { ignoreURLParametersMatching = [/^utm_/, /^fbclid$/], directoryIndex = "index.html", cleanURLs = true, urlManipulation } = {}) {
  const urlObject = new URL(url, location.href);
  urlObject.hash = "";
  yield urlObject.href;
  const urlWithoutIgnoredParams = removeIgnoredSearchParams(urlObject, ignoreURLParametersMatching);
  yield urlWithoutIgnoredParams.href;
  if (directoryIndex && urlWithoutIgnoredParams.pathname.endsWith("/")) {
    const directoryURL = new URL(urlWithoutIgnoredParams.href);
    directoryURL.pathname += directoryIndex;
    yield directoryURL.href;
  }
  if (cleanURLs) {
    const cleanURL = new URL(urlWithoutIgnoredParams.href);
    cleanURL.pathname += ".html";
    yield cleanURL.href;
  }
  if (urlManipulation) {
    const additionalURLs = urlManipulation({ url: urlObject });
    for (const urlToAttempt of additionalURLs) {
      yield urlToAttempt.href;
    }
  }
}
class PrecacheRoute extends Route {
  constructor(precacheController2, options) {
    const match = ({ request }) => {
      const urlsToCacheKeys = precacheController2.getURLsToCacheKeys();
      for (const possibleURL of generateURLVariations(request.url, options)) {
        const cacheKey = urlsToCacheKeys.get(possibleURL);
        if (cacheKey) {
          const integrity = precacheController2.getIntegrityForCacheKey(cacheKey);
          return { cacheKey, integrity };
        }
      }
      return;
    };
    super(match, precacheController2.strategy);
  }
}
function addRoute(options) {
  const precacheController2 = getOrCreatePrecacheController();
  const precacheRoute = new PrecacheRoute(precacheController2, options);
  registerRoute(precacheRoute);
}
function precache(entries) {
  const precacheController2 = getOrCreatePrecacheController();
  precacheController2.precache(entries);
}
function precacheAndRoute(entries, options) {
  precache(entries);
  addRoute(options);
}
const cacheOkAndOpaquePlugin = {
  cacheWillUpdate: async ({ response }) => {
    if (response.status === 200 || response.status === 0) {
      return response;
    }
    return null;
  }
};
class NetworkFirst extends Strategy {
  constructor(options = {}) {
    super(options);
    if (!this.plugins.some((p) => "cacheWillUpdate" in p)) {
      this.plugins.unshift(cacheOkAndOpaquePlugin);
    }
    this._networkTimeoutSeconds = options.networkTimeoutSeconds || 0;
  }
  async _handle(request, handler) {
    const logs = [];
    const promises = [];
    let timeoutId;
    if (this._networkTimeoutSeconds) {
      const { id, promise } = this._getTimeoutPromise({ request, logs, handler });
      timeoutId = id;
      promises.push(promise);
    }
    const networkPromise = this._getNetworkPromise({
      timeoutId,
      request,
      logs,
      handler
    });
    promises.push(networkPromise);
    const response = await handler.waitUntil((async () => {
      return await handler.waitUntil(Promise.race(promises)) || await networkPromise;
    })());
    if (!response) {
      throw new WorkboxError("no-response", { url: request.url });
    }
    return response;
  }
  _getTimeoutPromise({ request, logs, handler }) {
    let timeoutId;
    const timeoutPromise = new Promise((resolve) => {
      const onNetworkTimeout = async () => {
        resolve(await handler.cacheMatch(request));
      };
      timeoutId = setTimeout(onNetworkTimeout, this._networkTimeoutSeconds * 1e3);
    });
    return {
      promise: timeoutPromise,
      id: timeoutId
    };
  }
  async _getNetworkPromise({ timeoutId, request, logs, handler }) {
    let error;
    let response;
    try {
      response = await handler.fetchAndCachePut(request);
    } catch (fetchError) {
      if (fetchError instanceof Error) {
        error = fetchError;
      }
    }
    if (timeoutId) {
      clearTimeout(timeoutId);
    }
    if (error || !response) {
      response = await handler.cacheMatch(request);
    }
    return response;
  }
}
precacheAndRoute([{"revision":"b66f4c1dd4d563a9bfef48eabb0ecefc","url":"assets/[...all].2cd86af3.js"},{"revision":"37660e8dd7001d8d398a7a65e89dbbcf","url":"assets/[...all].52edc150.js"},{"revision":"7c407e79396ead745bb8adab6ffc191f","url":"assets/[...all].82118b1b.js"},{"revision":"33b2411a95bf7ebe212a66b7124dd397","url":"assets/[...all].cd05e41e.css"},{"revision":"e00251b117a1e8dad1c1a7227aa44fb6","url":"assets/app.4589602f.js"},{"revision":"229c5e9bc0f410fd9a72cc96c7baaa17","url":"assets/app.aa5b3a01.js"},{"revision":"cf86f343edda8bc28347caa1ca73e169","url":"assets/app.b0d0ad99.js"},{"revision":"d3124c1f431a4fb8f027f51a30d974d9","url":"assets/AppLayout.395c47d7.js"},{"revision":"7966c0baa613c136933976b8ca7159c9","url":"assets/AppLayout.644482c4.js"},{"revision":"5b1518935dd889f3d0e555d3c947fbbb","url":"assets/AppLayout.803edda9.css"},{"revision":"f4be0d492bd412ae0db3a732b14a49c8","url":"assets/AppLayout.8401e9d1.css"},{"revision":"6fac9b230b1361c1319dc7ad88a30260","url":"assets/AppLayout.dee93ce7.js"},{"revision":"ffde318219b56c39acd367f00a1f5fbe","url":"assets/auth.12dfe96d.js"},{"revision":"0de6f7099e754059a05364c77536cf74","url":"assets/auth.5cbcf99c.js"},{"revision":"49e3dad0d2f436723b49030e40acf48f","url":"assets/auth.7dbfcfa4.css"},{"revision":"e408b2b78454f472163a9a9c516697ed","url":"assets/auth.7e85f957.js"},{"revision":"efdc6a8f238eb52bad95db87a24f3637","url":"assets/background.5bd78b71.js"},{"revision":"f429f95c100a45468c453646506566ef","url":"assets/CategoriesNavBar.24c07de6.js"},{"revision":"141af8133a2c427d0d3a168bc48e3ce4","url":"assets/CategoriesNavBar.4a5d2d2a.js"},{"revision":"d717467734a7e2283646f4b1056e741b","url":"assets/CategoriesNavBar.d3d311da.js"},{"revision":"ebf37262c3d4c56cda0cfd22e89bb9b6","url":"assets/commands.22d157fe.css"},{"revision":"7527c706758c18679572d09bb0104fba","url":"assets/commands.47575e56.js"},{"revision":"3d1d4accbb5f55fa0b6d94859afd206d","url":"assets/commands.932fcca5.js"},{"revision":"bdbc7d31bb70e0614121a68761937694","url":"assets/commands.9be481c2.css"},{"revision":"ef821ee855591e65c123c5ed3e100fc1","url":"assets/commands.a5af7479.js"},{"revision":"13de834256d8db54a96ab030f122fb2f","url":"assets/delivery.0826e506.js"},{"revision":"05f7462792d02e0f49c83c315d79fbfe","url":"assets/delivery.5295eb40.js"},{"revision":"096cea2a27c370af0c9e69e3c2a69964","url":"assets/delivery.93f72544.js"},{"revision":"7227c7d5ead07b498f517d166e926db6","url":"assets/delivery.a5ce49a0.css"},{"revision":"22c6c9f624c862473f6fbed519d03b54","url":"assets/delivery.f1032eab.css"},{"revision":"72e78068f7ff0648792ce2825b3b425b","url":"assets/has-nested-router-link.4fe8dab1.js"},{"revision":"123abe86b1e20eefcf479d6fd0932333","url":"assets/html2canvas.esm.0eae2bf4.js"},{"revision":"50ac274490d11350aea159f8af664121","url":"assets/index.081ffdee.js"},{"revision":"423d6051e8fa79d332a679c57387077a","url":"assets/index.0c806c15.css"},{"revision":"4ef5ee3f4123c7b818ccd4ef268f89d7","url":"assets/index.1bcd6d59.css"},{"revision":"e4fb83d90db5596444b1d67408bf4a7f","url":"assets/index.26519675.js"},{"revision":"36df8d69efae8e67f68b5fd084da7f43","url":"assets/index.34e397f1.js"},{"revision":"4b2c29af4ebc989bc78b1383b52bd0f5","url":"assets/index.57b33d27.js"},{"revision":"c7b54f50bc9d0c9224b70668b05e3c0f","url":"assets/index.5eaf68d3.js"},{"revision":"d848a79a6327cc16488994b6c489c761","url":"assets/index.62fb4210.js"},{"revision":"95d7b9b657a5343e56bd16b9083b83dc","url":"assets/index.89180d3a.css"},{"revision":"66842f292da0bdd2ebb6fb8e708913ce","url":"assets/index.9ad3bf19.js"},{"revision":"1918e7059228ba12e0f160a50ea83d9d","url":"assets/index.9bb7e0d7.js"},{"revision":"6fc5ab28cac9170d5eb90374d8e9b7cd","url":"assets/index.9e06346e.css"},{"revision":"e23612f6b09491e3892d0b61e973a2ae","url":"assets/index.da2595e0.css"},{"revision":"56ef28fd36acec9fab2aaa096d724b34","url":"assets/index.e669fedc.js"},{"revision":"d3c28a65fae264c159c04e33804efef5","url":"assets/index.es.5c3cac3b.js"},{"revision":"4fedf926a1bb64fddd9a5a396aa92cdf","url":"assets/index.es.8a5dc338.js"},{"revision":"e2968cc23dcada5677eddc440d0e8f7e","url":"assets/index.es.8c0636ab.js"},{"revision":"355b4ecd8e968d833de245805e36c68d","url":"assets/index.f4679342.js"},{"revision":"96dc685e2929939a101bb37da87fd709","url":"assets/IsotipoMozoOficial.6b66971c.js"},{"revision":"5d12f4eaa2f85326ff4dc46b8ccbd52a","url":"assets/IsotipoMozoOficial.6fad2d75.css"},{"revision":"eeafeea0c74b28b5bb60200c28950adf","url":"assets/IsotipoMozoOficial.aa231484.js"},{"revision":"cb9c84536865128bacac1e0a7d1626ff","url":"assets/IsotipoMozoOficial.b3dc484a.js"},{"revision":"0b2f49bc5dbdde2fdf74a28e357dfdab","url":"assets/KanbanDropdown.2da28419.js"},{"revision":"0c67fc960910830a35270dfdbfb20c41","url":"assets/KanbanDropdown.a48fd2b4.js"},{"revision":"a1863562783ea4bea094106930e47066","url":"assets/KanbanDropdown.cc692e1f.js"},{"revision":"ae03607df9cab05195a092dff4c10199","url":"assets/LockedScreen.2b81f2a4.css"},{"revision":"42a34823fd1f32907986c68f89053ab3","url":"assets/LockedScreen.2bd45f3e.js"},{"revision":"f67e32b94f0dc2abd658ed42c26ef4d2","url":"assets/LockedScreen.b9852cf8.js"},{"revision":"51ac9ad7e52c6be50688831263f80697","url":"assets/LockedScreen.fd6784b9.js"},{"revision":"0ce4961d2af069c54cd78455c5b95d94","url":"assets/login.4a4b902c.js"},{"revision":"00ad104cb4a477d3393cfc260a3d16ba","url":"assets/login.6a0a1ba8.js"},{"revision":"cb333de4f69653a31456d54086cd6015","url":"assets/login.9400536f.js"},{"revision":"96ba9f520b5b0952c7a6d45758e09d0f","url":"assets/login.eec379cc.css"},{"revision":"69bf39aeefd3780d08cedc95368e5e65","url":"assets/LogoMozoOficial.0ad0d32e.css"},{"revision":"eb382b2020b22849d188418097a77d16","url":"assets/LogoMozoOficial.5b8fc42e.js"},{"revision":"05301b054abe6fd745def21a399c0cd5","url":"assets/LogoMozoOficial.aeb5e390.js"},{"revision":"a2302956e1e68713bd3c8dece38af45f","url":"assets/LogoMozoOficial.e1925fc8.js"},{"revision":"d6dabb0654a95317b95c7ebd5f18521f","url":"assets/masterService.18c6d5eb.js"},{"revision":"8d9ecfe411a856e282378c2ca20ac069","url":"assets/masterService.34b643b5.js"},{"revision":"162982fc79fde20beef3512bd15126c2","url":"assets/masterService.a210a3b8.js"},{"revision":"5b3e038232bfad6513e2797c26012356","url":"assets/mesas.3b36560b.js"},{"revision":"64f685b358ca9ea419fa95335a098132","url":"assets/mesas.9631f968.js"},{"revision":"9dae17440cd3af14175a796a9260f519","url":"assets/mesas.a1217be4.js"},{"revision":"66363f4b3af8c671f44e550aadeeee11","url":"assets/mesas.c0a856e1.js"},{"revision":"49ab9c22660773cd76b6966e16d241d7","url":"assets/mesas.c1ca1ab5.js"},{"revision":"e817c5de4e68883c2f3a6babb212112b","url":"assets/mesas.db0f11b2.css"},{"revision":"01fbe3346a41391597bf20cef4924317","url":"assets/mesas.e59a6800.css"},{"revision":"0f3eaead1952c48e36ad2bafa4c1cd04","url":"assets/mesas.f76cc4db.js"},{"revision":"ee602a73d857be8db2a5a6cb5eaa1dd5","url":"assets/MoveAmbienteModal.3ac7fa15.css"},{"revision":"89bd12b6afdb939479c323d75425d7e0","url":"assets/MoveAmbienteModal.5fb0e4a9.js"},{"revision":"2701212867dbb7ed57eb31ec39198b83","url":"assets/MoveAmbienteModal.7a404973.css"},{"revision":"bbc9223851a0e0168ef2f25326cb61fc","url":"assets/MoveAmbienteModal.aae0bd3b.js"},{"revision":"e12482d3a40a6dc97843c2cc4126beef","url":"assets/MoveAmbienteModal.e22f5e5c.js"},{"revision":"83865f61a82a59bf0411f210e3b0b953","url":"assets/mozo.0451a4b9.js"},{"revision":"2d483a51eab177fc6f38fdfcb5bab374","url":"assets/mozo.65ef66ce.js"},{"revision":"cb11675a017d7bdf8c9deb22fe13c581","url":"assets/mozo.7f1e164c.css"},{"revision":"2098c1d6023b3808e6b97a81dfb359d6","url":"assets/mozo.b96787ff.js"},{"revision":"81c35a235c743ba05a94568238f36e67","url":"assets/multiselect.312cdae5.js"},{"revision":"e37d6e829f99406dbd7b2a78d0444a80","url":"assets/multiselect.37d18953.js"},{"revision":"055ab2bdf9815f42509adae367e0e9cd","url":"assets/multiselect.46ddd5a4.js"},{"revision":"5db268f89a53c72b28efeb9a9744bebe","url":"assets/navbarLayoutState.407dc132.js"},{"revision":"12aace9bc569219fae50c005d5bc9fbc","url":"assets/navbarLayoutState.906dd9ac.js"},{"revision":"ab71f5ece4c0e472ca432b9c84b265a2","url":"assets/navbarLayoutState.e9420b5f.js"},{"revision":"384daa63a484a7c778883541621ab1f3","url":"assets/orders.457aa2e4.js"},{"revision":"377b7aa048a76439aabe42ceb17ecbcb","url":"assets/orders.7541ca59.js"},{"revision":"d44125e9b5369f15c815fcb82141466c","url":"assets/orders.a8b47658.css"},{"revision":"8c282ae780514058e1d6af8d8c882d31","url":"assets/orders.e5bce046.js"},{"revision":"3c43db71c6450299df494c8c11039a6c","url":"assets/PedidoEnvironment.3813b644.js"},{"revision":"798746a1809ed26ebca5e664a9125410","url":"assets/PedidoEnvironment.60bff902.js"},{"revision":"3d3dcce84d3bf9cdc04d31c8d41f99e6","url":"assets/PedidoEnvironment.a4175360.js"},{"revision":"5f1e8c5640af5bf9af33524917c6ce31","url":"assets/PedidoEnvironment.b5659766.css"},{"revision":"4a61535315c8a3b85c9f563190daff42","url":"assets/plugin-vue_export-helper.5a098b48.js"},{"revision":"d7909ae0d6d84f131a06db014f2d1e23","url":"assets/pos.39592f95.js"},{"revision":"aa6a1e7fa9c15859b016f965d51ba78d","url":"assets/pos.3f985d2e.js"},{"revision":"dc39712f997e84c6231d662a2730ba19","url":"assets/pos.8d7ccb9b.js"},{"revision":"53e2a9beed2d192f9726034ef2b17cac","url":"assets/pos.b0d12d1d.js"},{"revision":"cb0b329dae96520cc59de7cdda9041ab","url":"assets/pos.c1bb1f5c.js"},{"revision":"341304ca61e2e55ab48d95ae9b33f789","url":"assets/pos.e08cafb9.js"},{"revision":"20ed7739d08a3dfa60bf7db24d283b4e","url":"assets/prices.29f6692b.js"},{"revision":"81155c42fec628850c66b0b260b925a6","url":"assets/prices.676b9b4e.css"},{"revision":"e4bb6ae07d9a5089bb0eabaed8f6ea35","url":"assets/prices.7ffa1105.js"},{"revision":"6e5774d7a47b069ebc8d05def099c70e","url":"assets/prices.b7c15ea4.js"},{"revision":"a35c6495cf8ecd4cb6a52673e8f2192c","url":"assets/ProductModifiersDialog.41eccd58.css"},{"revision":"24a9168a851abbfad73f1de1368c8753","url":"assets/ProductModifiersDialog.4340dfa2.js"},{"revision":"35ddbc0f034b2b50ecdebb8de02a75ed","url":"assets/ProductModifiersDialog.4f25fc1a.css"},{"revision":"af9d0c810d2c95b0220741b84320410f","url":"assets/ProductModifiersDialog.aaf4855c.js"},{"revision":"9cd34fb05d29cc668d7ab234733a4783","url":"assets/ProductModifiersDialog.ce9208d0.css"},{"revision":"f17d31eb8f49e10d9d7a3b1d8f684521","url":"assets/purify.es.82af1ade.js"},{"revision":"7d68b83dcc5dcfd58f58042f5181bd97","url":"assets/realtime.5af6cc83.js"},{"revision":"51680a1f369e67b8374fc2bdcc0accda","url":"assets/realtime.ae8fa929.js"},{"revision":"d39deb58ea8c0f26095fcf843200d677","url":"assets/restaurantService.76a73e4a.js"},{"revision":"9545b4bb5f28055767f0f04d8c32f9a3","url":"assets/sidebarLayoutState.3ce0e310.js"},{"revision":"5e2863b14fbfd01cced1e6c76c183e69","url":"assets/sidebarLayoutState.5ae46904.js"},{"revision":"bfbeaf866ceb4d7277fa966db57b536c","url":"assets/sidebarLayoutState.d444e432.js"},{"revision":"faad36c4bc365267a24a9a0f5c240cce","url":"assets/signup.a029f3ce.js"},{"revision":"98c55053886e14cf46caa7320b3e8c49","url":"assets/signup.a65d6d3e.js"},{"revision":"576418a3886b902065efe24cce4e970a","url":"assets/signup.ffa600a4.js"},{"revision":"60e70c4aca890bdb9d47da115e5cd7ba","url":"assets/slider.af31fdd4.js"},{"revision":"c87ca80dd083f6d0e7e6137591f3768e","url":"assets/slider.eff6de73.js"},{"revision":"91b1a341b0e21b378f93bef97cec4345","url":"assets/slider.f6a6eb1d.js"},{"revision":"8a009dc1f6a607f082ef1de15cda0637","url":"assets/takeaway.7c034002.js"},{"revision":"4a8b08829b970a9fc184b78abf2f796a","url":"assets/takeaway.968c5ab1.css"},{"revision":"ead545300c2f86efc93eedcb12525c24","url":"assets/takeaway.97ee705b.js"},{"revision":"499162ed0e775e4e37ef784b2cfbe6c4","url":"assets/takeaway.9c5642bb.js"},{"revision":"8a4e5bb33939728866e83680143b0025","url":"assets/takeaway.f382de4a.css"},{"revision":"a919e07cc439a2e336d14a0ae71f119a","url":"assets/tooltip.24128ff9.js"},{"revision":"55bd23af5393e1368fb9b533c7e2e667","url":"assets/VAvatar.4eca5934.js"},{"revision":"c6766f716a8d8535607729b4e206392b","url":"assets/VAvatar.8a46791f.js"},{"revision":"d2f2dc40719a39ca85fbfad472744715","url":"assets/VAvatar.916bc669.js"},{"revision":"7f8212d799e9e0bd2ed5c93de669fe10","url":"assets/VButton.2bd31a3c.js"},{"revision":"8acca82102a4a10fd412dad7158ecdab","url":"assets/VButton.2be3e296.js"},{"revision":"4443802dd30dd2d21590955e2958b165","url":"assets/VButton.e28c104e.css"},{"revision":"b2eb64c86f19e357f4851d23f6baab3a","url":"assets/VButton.fbae0555.js"},{"revision":"1ac57c116b32a82f128440f28fe90518","url":"assets/VControl.243637c8.css"},{"revision":"9e7c95b2f8a44327e44b37cb320f11e6","url":"assets/VControl.3ac22e58.js"},{"revision":"ebe1d48ef3efe7fb47892cb4419cdc7e","url":"assets/VControl.5ab3a926.js"},{"revision":"9fdd6b6b2f74791c81b92ebe5a67696e","url":"assets/VControl.ab20f615.js"},{"revision":"296aaff620fe8039b427b6e06eee3046","url":"assets/VDropdown.0f83e5f1.css"},{"revision":"727ddf7a65accb945d852740a39fe54f","url":"assets/VDropdown.30a2a102.js"},{"revision":"3c84e5d9156fe86bd44e5c6b77ec3b4a","url":"assets/VDropdown.612abd01.js"},{"revision":"ff4a85e5d74924e876b51ce2d0db673a","url":"assets/VDropdown.d0ee03b8.js"},{"revision":"af88fb398e1cbf3d3bd4dc69b615a2d5","url":"assets/vendor.0611facb.js"},{"revision":"da7197d837171ca28d991ce184847959","url":"assets/vendor.dca42141.js"},{"revision":"1b850867aa24686a8b527293000573ab","url":"assets/vendor.f54a8f4c.js"},{"revision":"1df37f7df758a1d11b3d0b2fb53162cf","url":"assets/VField.04345baa.js"},{"revision":"73efa692d34f227dc0dd866dc7f5ff9c","url":"assets/VField.0b883ad8.js"},{"revision":"dc9ea9a384e1db56995d500c7de23866","url":"assets/VField.547aede3.js"},{"revision":"f0b4207688c9a4bafe3cc97275c99b7e","url":"assets/VIcon.394dd7c3.js"},{"revision":"1fe6d816bc3620ffed1c6a70268d0892","url":"assets/VIcon.3f8eb681.js"},{"revision":"80e52265246c05f04e558e6635be9f49","url":"assets/VIcon.90bdc252.js"},{"revision":"df6d08deffbcc809f40b8cc1f84b1a1f","url":"assets/VIconButton.03fee79f.js"},{"revision":"e565e024af3b26c2213977d65f160008","url":"assets/VIconButton.043495e6.js"},{"revision":"e674175c1c0676fcde04b765868e949c","url":"assets/VIconButton.856daf8c.js"},{"revision":"8a6964948a30b5b83df49a2b1661c447","url":"assets/VLoader.0f2164f0.js"},{"revision":"750404a04fda0ce398ad2a6bd6a03966","url":"assets/VLoader.789890da.js"},{"revision":"74182f801c56bed6a87142514737d4bc","url":"assets/VLoader.9ad9c176.js"},{"revision":"c4958bc73557d6a1a079acb97d48611a","url":"assets/VModal.95874e8d.js"},{"revision":"683ff44f538607718cdd8aeb20e93e29","url":"assets/VModal.d2e0a5eb.js"},{"revision":"2efa864dca2c26bfcaa1a4826eb9504f","url":"assets/VModal.d8de09e0.css"},{"revision":"1d63a54c4ffb9d568368794c70b9c67d","url":"assets/VModal.fa3cd151.js"},{"revision":"b718bf701abd05518e721f4387edfb52","url":"assets/VPlaceloadWrap.0c90a0a2.js"},{"revision":"ccab17e8eebb000497acf65f3e0e097b","url":"assets/VPlaceloadWrap.4a1de6e8.js"},{"revision":"1e49fbc819a72ed91e4086069b2191fa","url":"assets/VPlaceloadWrap.89d5fae9.js"},{"revision":"7dabefe32c90161e6018eb9fd1f65085","url":"assets/vue-tippy.esm-bundler.102701f8.js"},{"revision":"4372e004c0ad78854e2f1d1347d1028f","url":"assets/vue-tippy.esm-bundler.394f106d.js"},{"revision":"b965bde151c65e63264f9a8dbaa0a27e","url":"assets/vue-tippy.esm-bundler.8fd45e2e.js"},{"revision":"ef92aabe2ad8387e1710b191ace5ccbe","url":"index.html"},{"revision":"4c8b74382b4f6b2cf5f8afcb87e80abc","url":"vendors/font-awesome-v5.css"},{"revision":"4bb4c5797d6ce8bd02b13e2d12c34bcd","url":"vendors/line-icons-pro.css"},{"revision":"84dcb5fdcc61a1daadf6607b40bd09ed","url":"vendors/loader.js"},{"revision":"238822f024eb9bd172d4d6494cacd69c","url":"vendors/prism-coldark-cold.css"},{"revision":"b456583f1253085e887d499352b92721","url":"favicon.svg"},{"revision":"2608995d3ce047aed1b4f12314b971e6","url":"favicon.ico"},{"revision":"f77c87f977e0fcce05a6df46c885a129","url":"robots.txt"},{"revision":"b1fc7ba21cbe0c252ddf4e374dff5bcf","url":"apple-touch-icon.png"},{"revision":"598ac9f6ba4777c6a0839a61f484cc95","url":"pwa-192x192.png"},{"revision":"fdde4a327d6c825b405236efbb8da6e3","url":"pwa-512x512.png"},{"revision":"83a4e305820b776324cb0e755cfd7a78","url":"manifest.webmanifest"}]);
registerRoute(({ url }) => url.href.startsWith("https"), new NetworkFirst());
