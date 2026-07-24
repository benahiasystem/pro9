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
precacheAndRoute([{"revision":"7c407e79396ead745bb8adab6ffc191f","url":"assets/[...all].82118b1b.js"},{"revision":"33b2411a95bf7ebe212a66b7124dd397","url":"assets/[...all].cd05e41e.css"},{"revision":"0427f03871b984d2c78a3a28cf759524","url":"assets/app.b11b04ed.js"},{"revision":"f4be0d492bd412ae0db3a732b14a49c8","url":"assets/AppLayout.8401e9d1.css"},{"revision":"5bae984023384fdc5f19f42816da899f","url":"assets/AppLayout.a7a5ca1c.js"},{"revision":"0de6f7099e754059a05364c77536cf74","url":"assets/auth.5cbcf99c.js"},{"revision":"49e3dad0d2f436723b49030e40acf48f","url":"assets/auth.7dbfcfa4.css"},{"revision":"efdc6a8f238eb52bad95db87a24f3637","url":"assets/background.5bd78b71.js"},{"revision":"35b9d59a1e6aa5c9be8816a7dc5ffa41","url":"assets/CategoriesNavBar.ae8819ff.js"},{"revision":"ebf37262c3d4c56cda0cfd22e89bb9b6","url":"assets/commands.22d157fe.css"},{"revision":"52679992b923e9869a576d9070f033fe","url":"assets/commands.29e8cdc7.js"},{"revision":"aa4ba9818bfba4a13eb4931e50713925","url":"assets/delivery.8e6787f2.js"},{"revision":"22c6c9f624c862473f6fbed519d03b54","url":"assets/delivery.f1032eab.css"},{"revision":"72e78068f7ff0648792ce2825b3b425b","url":"assets/has-nested-router-link.4fe8dab1.js"},{"revision":"123abe86b1e20eefcf479d6fd0932333","url":"assets/html2canvas.esm.0eae2bf4.js"},{"revision":"36df8d69efae8e67f68b5fd084da7f43","url":"assets/index.34e397f1.js"},{"revision":"fa6bfed5629cd735f9c84350ba118af9","url":"assets/index.642b1a7d.js"},{"revision":"95d7b9b657a5343e56bd16b9083b83dc","url":"assets/index.89180d3a.css"},{"revision":"458324fb7cb12549e62502b4a192cc6f","url":"assets/index.8c6daf4a.js"},{"revision":"6fc5ab28cac9170d5eb90374d8e9b7cd","url":"assets/index.9e06346e.css"},{"revision":"56ef28fd36acec9fab2aaa096d724b34","url":"assets/index.e669fedc.js"},{"revision":"e2968cc23dcada5677eddc440d0e8f7e","url":"assets/index.es.8c0636ab.js"},{"revision":"5d12f4eaa2f85326ff4dc46b8ccbd52a","url":"assets/IsotipoMozoOficial.6fad2d75.css"},{"revision":"eeafeea0c74b28b5bb60200c28950adf","url":"assets/IsotipoMozoOficial.aa231484.js"},{"revision":"0b2f49bc5dbdde2fdf74a28e357dfdab","url":"assets/KanbanDropdown.2da28419.js"},{"revision":"ae03607df9cab05195a092dff4c10199","url":"assets/LockedScreen.2b81f2a4.css"},{"revision":"6f544f65233135ef382fce7b3a92cd85","url":"assets/LockedScreen.aebfc2f6.js"},{"revision":"ecf149ce29086863cbcbc3a01876287e","url":"assets/login.256e2236.js"},{"revision":"96ba9f520b5b0952c7a6d45758e09d0f","url":"assets/login.eec379cc.css"},{"revision":"69bf39aeefd3780d08cedc95368e5e65","url":"assets/LogoMozoOficial.0ad0d32e.css"},{"revision":"05301b054abe6fd745def21a399c0cd5","url":"assets/LogoMozoOficial.aeb5e390.js"},{"revision":"0cda73c8b0acfbff6a4b5cab9a606d7c","url":"assets/masterService.282e9ea7.js"},{"revision":"6e6bfe70e8e6a9c9e69c5d6fe81e8bf0","url":"assets/mesas.0e2ecede.js"},{"revision":"415b7de8a281b2542b0701535a067f1d","url":"assets/mesas.d2f83c5c.js"},{"revision":"01fbe3346a41391597bf20cef4924317","url":"assets/mesas.e59a6800.css"},{"revision":"2701212867dbb7ed57eb31ec39198b83","url":"assets/MoveAmbienteModal.7a404973.css"},{"revision":"0e828042527882184683c180baea4658","url":"assets/MoveAmbienteModal.a56127d8.js"},{"revision":"cb11675a017d7bdf8c9deb22fe13c581","url":"assets/mozo.7f1e164c.css"},{"revision":"6fca94d26d1556d43a46efd054ec5efe","url":"assets/mozo.ae465d8c.js"},{"revision":"e37d6e829f99406dbd7b2a78d0444a80","url":"assets/multiselect.37d18953.js"},{"revision":"427c7a936f51eada7e916594bbed6f45","url":"assets/navbarLayoutState.af10f214.js"},{"revision":"d44125e9b5369f15c815fcb82141466c","url":"assets/orders.a8b47658.css"},{"revision":"446a32dfb7c34e21a27b3c332c5e3526","url":"assets/orders.bc45ae1c.js"},{"revision":"702675d0c282c58b9cd5cf99fbd023e8","url":"assets/PedidoEnvironment.56535c10.js"},{"revision":"5f1e8c5640af5bf9af33524917c6ce31","url":"assets/PedidoEnvironment.b5659766.css"},{"revision":"4a61535315c8a3b85c9f563190daff42","url":"assets/plugin-vue_export-helper.5a098b48.js"},{"revision":"d7909ae0d6d84f131a06db014f2d1e23","url":"assets/pos.39592f95.js"},{"revision":"dc39712f997e84c6231d662a2730ba19","url":"assets/pos.8d7ccb9b.js"},{"revision":"81155c42fec628850c66b0b260b925a6","url":"assets/prices.676b9b4e.css"},{"revision":"f19cf7f221a03270c99f8b39cda9a529","url":"assets/prices.aab9519d.js"},{"revision":"215661a48278a4b879d679202ea6ab08","url":"assets/ProductModifiersDialog.2789ba09.js"},{"revision":"9cd34fb05d29cc668d7ab234733a4783","url":"assets/ProductModifiersDialog.ce9208d0.css"},{"revision":"f17d31eb8f49e10d9d7a3b1d8f684521","url":"assets/purify.es.82af1ade.js"},{"revision":"9e2d8536ca54b1ba50ed79cc7f97f4c4","url":"assets/restaurantService.923b86e9.js"},{"revision":"bfbeaf866ceb4d7277fa966db57b536c","url":"assets/sidebarLayoutState.d444e432.js"},{"revision":"d0415b82b252e461e76ff2b8971d0fa7","url":"assets/signup.858b6637.js"},{"revision":"91b1a341b0e21b378f93bef97cec4345","url":"assets/slider.f6a6eb1d.js"},{"revision":"25034964d0dcc72706fbeb0df35d6993","url":"assets/takeaway.57d8140a.js"},{"revision":"8a4e5bb33939728866e83680143b0025","url":"assets/takeaway.f382de4a.css"},{"revision":"a919e07cc439a2e336d14a0ae71f119a","url":"assets/tooltip.24128ff9.js"},{"revision":"55bd23af5393e1368fb9b533c7e2e667","url":"assets/VAvatar.4eca5934.js"},{"revision":"7f8212d799e9e0bd2ed5c93de669fe10","url":"assets/VButton.2bd31a3c.js"},{"revision":"4443802dd30dd2d21590955e2958b165","url":"assets/VButton.e28c104e.css"},{"revision":"1ac57c116b32a82f128440f28fe90518","url":"assets/VControl.243637c8.css"},{"revision":"9fdd6b6b2f74791c81b92ebe5a67696e","url":"assets/VControl.ab20f615.js"},{"revision":"296aaff620fe8039b427b6e06eee3046","url":"assets/VDropdown.0f83e5f1.css"},{"revision":"727ddf7a65accb945d852740a39fe54f","url":"assets/VDropdown.30a2a102.js"},{"revision":"da7197d837171ca28d991ce184847959","url":"assets/vendor.dca42141.js"},{"revision":"dc9ea9a384e1db56995d500c7de23866","url":"assets/VField.547aede3.js"},{"revision":"f0b4207688c9a4bafe3cc97275c99b7e","url":"assets/VIcon.394dd7c3.js"},{"revision":"df6d08deffbcc809f40b8cc1f84b1a1f","url":"assets/VIconButton.03fee79f.js"},{"revision":"74182f801c56bed6a87142514737d4bc","url":"assets/VLoader.9ad9c176.js"},{"revision":"2efa864dca2c26bfcaa1a4826eb9504f","url":"assets/VModal.d8de09e0.css"},{"revision":"1d63a54c4ffb9d568368794c70b9c67d","url":"assets/VModal.fa3cd151.js"},{"revision":"ccab17e8eebb000497acf65f3e0e097b","url":"assets/VPlaceloadWrap.4a1de6e8.js"},{"revision":"7dabefe32c90161e6018eb9fd1f65085","url":"assets/vue-tippy.esm-bundler.102701f8.js"},{"revision":"c389598553158be8c425e5f25691dabd","url":"index.html"},{"revision":"4c8b74382b4f6b2cf5f8afcb87e80abc","url":"vendors/font-awesome-v5.css"},{"revision":"4bb4c5797d6ce8bd02b13e2d12c34bcd","url":"vendors/line-icons-pro.css"},{"revision":"84dcb5fdcc61a1daadf6607b40bd09ed","url":"vendors/loader.js"},{"revision":"238822f024eb9bd172d4d6494cacd69c","url":"vendors/prism-coldark-cold.css"},{"revision":"b456583f1253085e887d499352b92721","url":"favicon.svg"},{"revision":"2608995d3ce047aed1b4f12314b971e6","url":"favicon.ico"},{"revision":"f77c87f977e0fcce05a6df46c885a129","url":"robots.txt"},{"revision":"b1fc7ba21cbe0c252ddf4e374dff5bcf","url":"apple-touch-icon.png"},{"revision":"598ac9f6ba4777c6a0839a61f484cc95","url":"pwa-192x192.png"},{"revision":"fdde4a327d6c825b405236efbb8da6e3","url":"pwa-512x512.png"},{"revision":"83a4e305820b776324cb0e755cfd7a78","url":"manifest.webmanifest"}]);
registerRoute(({ url }) => url.href.startsWith("https"), new NetworkFirst());
