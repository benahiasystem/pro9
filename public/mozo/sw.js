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
precacheAndRoute([{"revision":"0bcb8d1a474af91a70287ecc8196a4fd","url":"assets/[...all].2cd86af3.js"},{"revision":"bada8c90a3412398f4415b3559d42359","url":"assets/[...all].52edc150.js"},{"revision":"837a4a56be31384cc87dbe626716840f","url":"assets/[...all].82118b1b.js"},{"revision":"251be4b360d52561f7aa5b8d78e5fbbb","url":"assets/[...all].c8f57a77.js"},{"revision":"33b2411a95bf7ebe212a66b7124dd397","url":"assets/[...all].cd05e41e.css"},{"revision":"92cb9874cdf82b590a82188c4c803337","url":"assets/[...all].e2c8d733.js"},{"revision":"c5eff373d3df6719cf749ef69beb261e","url":"assets/app.2264b4b8.js"},{"revision":"48aa389556d83047361d13fd20b6cbd1","url":"assets/app.4589602f.js"},{"revision":"bb9ae4e5f2307e5151a068892cf82d96","url":"assets/app.68052cc5.js"},{"revision":"2258bba4603884d8a7a0fb55d92811e1","url":"assets/app.9efd87e7.js"},{"revision":"2a7feb8b71cf75e01957f8fd8a408a25","url":"assets/app.aa5b3a01.js"},{"revision":"20911e65c5d922bfbb52fa55e9deef96","url":"assets/app.b0d0ad99.js"},{"revision":"aa048a5d99c3ae378abe8611e90225f4","url":"assets/app.d3e05c7b.js"},{"revision":"4429d2f8385498aec6f8ae3f8d486a57","url":"assets/app.ec437f83.js"},{"revision":"6471afe313df36012b57acc2add68834","url":"assets/AppLayout.395c47d7.js"},{"revision":"0cd103e009b5360b0144bf6f5cecfea4","url":"assets/AppLayout.55b8b567.js"},{"revision":"cce9e6882a181c6c74beffa019bb9ddd","url":"assets/AppLayout.5895d4a9.js"},{"revision":"7629254f2727ac6f977d4f6b3d5b0fca","url":"assets/AppLayout.644482c4.js"},{"revision":"ae0b77f79bb92222de5ee953dbc4be3b","url":"assets/AppLayout.803edda9.css"},{"revision":"6d914ec1b1775aa4e0e64fc034cb0148","url":"assets/AppLayout.8401e9d1.css"},{"revision":"27ea3e044a889cb7df7199deb4e756d9","url":"assets/AppLayout.a1b351cc.js"},{"revision":"5e89f59d7dbf68bbe70f65bfdc8bb04c","url":"assets/AppLayout.a694544e.css"},{"revision":"677fb0155d08eb739861c538eba62e2f","url":"assets/AppLayout.bc78f6dd.js"},{"revision":"4f0f10385db28744e02afaa59a702c05","url":"assets/AppLayout.dee93ce7.js"},{"revision":"82a28ce042802a96a3ab4e159aeb4073","url":"assets/AppLayout.fb6c09b1.js"},{"revision":"941dd04d090d605827efa01d46033d73","url":"assets/auth.12dfe96d.js"},{"revision":"2c61d3ba0d331bd9e2dc448ebfb26011","url":"assets/auth.5cbcf99c.js"},{"revision":"49e3dad0d2f436723b49030e40acf48f","url":"assets/auth.7dbfcfa4.css"},{"revision":"ede0b318f1a8c63168610b4783525fb4","url":"assets/auth.7e85f957.js"},{"revision":"8daf2bf68bfcfcc027b75566645a935f","url":"assets/auth.855a1ae1.js"},{"revision":"421558120720da7248b42cf91d35e215","url":"assets/auth.b5691fa3.js"},{"revision":"efdc6a8f238eb52bad95db87a24f3637","url":"assets/background.5bd78b71.js"},{"revision":"4beb0199f4df51bc0dd22ac3b5f936d6","url":"assets/CategoriesNavBar.04f8686d.js"},{"revision":"8beb3833aca2192d61a241e116eaebd9","url":"assets/CategoriesNavBar.24c07de6.js"},{"revision":"8dff0ef93c1a6184dfe335c140196eea","url":"assets/CategoriesNavBar.4a5d2d2a.js"},{"revision":"58c64f05bfea19f59ab21ad81332d57a","url":"assets/CategoriesNavBar.a7a81745.js"},{"revision":"85c08343fd06a2a7a41709a692a0304b","url":"assets/CategoriesNavBar.ab01c07c.js"},{"revision":"6fe42433e21b1b82117ffbba9751951d","url":"assets/CategoriesNavBar.c790bc8e.js"},{"revision":"bf994682dcbfcd5f49b2643ab5ef837e","url":"assets/CategoriesNavBar.d3d311da.js"},{"revision":"67b7fe48997efaf0003bd273a4cf7cd5","url":"assets/CategoriesNavBar.dec1a81e.js"},{"revision":"d5434b5d1e5cb0a315ab609b0a910ffd","url":"assets/commands.114b03f6.js"},{"revision":"2d2d923a6c2bcd53b4e5ea7a16bdddf5","url":"assets/commands.22d157fe.css"},{"revision":"dc83b83cc2812923cc308d08d6f3b7cc","url":"assets/commands.47575e56.js"},{"revision":"244297c4bd6d968a46dfd67d53479bf0","url":"assets/commands.8e548c95.js"},{"revision":"83b501c9a5a0b51a48f7ec92c32855c6","url":"assets/commands.932fcca5.js"},{"revision":"b2ac2726d8a7b29534a33fbf29863ce8","url":"assets/commands.93dea282.js"},{"revision":"bdbc7d31bb70e0614121a68761937694","url":"assets/commands.9be481c2.css"},{"revision":"a5cfb84eecae14a855c9f06bf6b8c9e9","url":"assets/commands.a5af7479.js"},{"revision":"efd80815423a30ee53c20dd86dcdb130","url":"assets/commands.c0288e18.js"},{"revision":"f7fc2095def226f91712317209909e27","url":"assets/commands.cf5c8363.js"},{"revision":"fb8e8da39a2096a9ae6dbe9f408c007e","url":"assets/delivery.0826e506.js"},{"revision":"146e4badc2b2cc906101ca39a69333b7","url":"assets/delivery.104f532b.css"},{"revision":"f9130b284e10a4c35f7a7281919d1573","url":"assets/delivery.2af7851d.js"},{"revision":"952d509ac412e5955be76b6109a072ad","url":"assets/delivery.4414a14b.js"},{"revision":"b3ea4aa33eee8c2de942f9f46d676ba3","url":"assets/delivery.46abb426.js"},{"revision":"d46594a5e04b3bf0e884217bd7244793","url":"assets/delivery.5295eb40.js"},{"revision":"4dd1a12a5eeb2313711c733bb34d758c","url":"assets/delivery.81c75c6f.js"},{"revision":"86ce517ca6ebffdd7b1facf69fbb9227","url":"assets/delivery.93f72544.js"},{"revision":"4d4131871961484978593d1160c4cff8","url":"assets/delivery.a5ce49a0.css"},{"revision":"26fea283836e13edda8210f85e87a259","url":"assets/delivery.a90cbdc7.js"},{"revision":"34f6ee1e8bbd5fc890457f8b6cc3f2e5","url":"assets/delivery.db0907c1.css"},{"revision":"de8854face94fbee3adf975b8b9d44fc","url":"assets/delivery.f1032eab.css"},{"revision":"72e78068f7ff0648792ce2825b3b425b","url":"assets/has-nested-router-link.4fe8dab1.js"},{"revision":"123abe86b1e20eefcf479d6fd0932333","url":"assets/html2canvas.esm.0eae2bf4.js"},{"revision":"55a063909ab5ebd809ccbd274d46c597","url":"assets/index.02f4974c.js"},{"revision":"fd76f8082749dc241aced4f34f49477f","url":"assets/index.081ffdee.js"},{"revision":"f5624655387666b8334d3618628631a6","url":"assets/index.0c806c15.css"},{"revision":"4ef5ee3f4123c7b818ccd4ef268f89d7","url":"assets/index.1bcd6d59.css"},{"revision":"bbc25ed90e90bdea709cb2ece85d286d","url":"assets/index.26519675.js"},{"revision":"3817197bac0a18c6ec82c556b0319e65","url":"assets/index.2b85a7aa.js"},{"revision":"33bcd7886853af16e5ced0460f3eeae9","url":"assets/index.30529855.js"},{"revision":"36df8d69efae8e67f68b5fd084da7f43","url":"assets/index.34e397f1.js"},{"revision":"b282d87ef3a2a3d559879dac9cc72018","url":"assets/index.4a46f1be.js"},{"revision":"cbe42ed69a1bab5ba7716bd10d6aaffa","url":"assets/index.57b33d27.js"},{"revision":"673bb8f5bf35f9035aeccef99b5e53fd","url":"assets/index.5eaf68d3.js"},{"revision":"3bb2bf27f92f83070ec6e7ccc09c1619","url":"assets/index.5f23ec3b.js"},{"revision":"1dbf85796c68b98d4ec675d8bf79f572","url":"assets/index.62fb4210.js"},{"revision":"f6790b67dbd755122274fcd8d0398276","url":"assets/index.751953dd.js"},{"revision":"3a43b7a094653dc0ee9cbc1291683098","url":"assets/index.7eb06bcc.js"},{"revision":"c9bb4c0fe87beef7002cce940000d6c4","url":"assets/index.81dc4c25.js"},{"revision":"b17baedf7297b313a22f4f39988bc52b","url":"assets/index.89180d3a.css"},{"revision":"bd8c8b9d76fbaa82244fc0a46090404a","url":"assets/index.94ca4d67.css"},{"revision":"73e573b5bfa98b34b711c0fb423d3993","url":"assets/index.9ad3bf19.js"},{"revision":"f63a2feeb848a5cde9e09cf547559dcc","url":"assets/index.9bb7e0d7.js"},{"revision":"c580611a80a85dac4d87a221d6c8d484","url":"assets/index.9e06346e.css"},{"revision":"6bd4f070b56f9f1b2e57db88206d2b1c","url":"assets/index.b0f716c7.js"},{"revision":"da00c47a8445e7d04e838b35014ba081","url":"assets/index.d3c60d3e.js"},{"revision":"4f874c3b2435f1e655ff50c61a48585d","url":"assets/index.da2595e0.css"},{"revision":"900e6df61710919e4580a5407eb99367","url":"assets/index.de978ae1.js"},{"revision":"29e98f03185fdd56b46a56e0d178a8a2","url":"assets/index.df0b1428.js"},{"revision":"5b72da6a71f767743efdd0fb6d2459ca","url":"assets/index.e669fedc.js"},{"revision":"cf6646178133a3364159075d194489c4","url":"assets/index.es.5c3cac3b.js"},{"revision":"d0ea6054c6ce0a613071c53cfc1014f1","url":"assets/index.es.8a5dc338.js"},{"revision":"95a27b0ac8fe525d79bb3bbadc38d7f4","url":"assets/index.es.8c0636ab.js"},{"revision":"e5d651a127615fdbb332c2fe22e4868d","url":"assets/index.es.acf1919e.js"},{"revision":"97519020e62fe87c99381fc2616e9857","url":"assets/index.es.dbb38847.js"},{"revision":"94f770d9257561667e71fa43860a0425","url":"assets/index.f4679342.js"},{"revision":"db8c8f16844eb2b875a4f695d605bb67","url":"assets/IsotipoMozoOficial.6b66971c.js"},{"revision":"5d12f4eaa2f85326ff4dc46b8ccbd52a","url":"assets/IsotipoMozoOficial.6fad2d75.css"},{"revision":"0656cf39bb55b75d3d1298b13e967712","url":"assets/IsotipoMozoOficial.9e2f6595.js"},{"revision":"c82ecfa47bebb67fc179a7cd71f98d0a","url":"assets/IsotipoMozoOficial.a038b280.js"},{"revision":"0c424497e7bd6d6e3870034af6a9f09a","url":"assets/IsotipoMozoOficial.aa231484.js"},{"revision":"941fc4cafe5a6c97ef5845648ee6308f","url":"assets/IsotipoMozoOficial.b3dc484a.js"},{"revision":"c9944a583ab02d4308f66811024eabcb","url":"assets/KanbanDropdown.2da28419.js"},{"revision":"6cda09a475b9d363a3e4b42f00c57dec","url":"assets/KanbanDropdown.2e9b5d1a.js"},{"revision":"f607e55468cc14bd8e4de958e5c87d9f","url":"assets/KanbanDropdown.78bdd639.js"},{"revision":"47f0dc1a884d8b7b182a13e6e531c2a3","url":"assets/KanbanDropdown.a48fd2b4.js"},{"revision":"c0a39737ad87cbd3a3a49de7936196bc","url":"assets/KanbanDropdown.cc692e1f.js"},{"revision":"e3ad5f1a4c7fb91291ed8d6994649a92","url":"assets/LockedScreen.23a9b310.js"},{"revision":"ae03607df9cab05195a092dff4c10199","url":"assets/LockedScreen.2b81f2a4.css"},{"revision":"d1415cef9b0aed6046b2421b74dd0c7e","url":"assets/LockedScreen.2bd45f3e.js"},{"revision":"a43c72d5ee559b1e757ea83d1557c143","url":"assets/LockedScreen.4c614724.js"},{"revision":"a9f7cc38dcf40348fc95ab7db7d52b7b","url":"assets/LockedScreen.605d5b59.js"},{"revision":"973ecbef087aecf1bb2bababc89c806a","url":"assets/LockedScreen.96cb3d65.js"},{"revision":"774b9342aedacd1bf3b59e7194db31c6","url":"assets/LockedScreen.ac14178a.js"},{"revision":"346ec0d6f472d72677bfd0a179dcb838","url":"assets/LockedScreen.b9852cf8.js"},{"revision":"65827bf29881a8fd1fce811a7b4c2ee5","url":"assets/LockedScreen.fd6784b9.js"},{"revision":"21dba18bff7f23b6a93b1d386f4e0d27","url":"assets/login.302c1dc0.js"},{"revision":"2f60cbe9786b18cb6c051a5a2656e497","url":"assets/login.41789d13.js"},{"revision":"fb6d04c51f0eb81a590e727c9dff1c29","url":"assets/login.4a4b902c.js"},{"revision":"14bc0813701499c0d37be8175744bca3","url":"assets/login.5e34f127.js"},{"revision":"d18dfe39bd33f685335c93d892bd1756","url":"assets/login.6a0a1ba8.js"},{"revision":"12195259003d85c5d31725b74a638f89","url":"assets/login.9400536f.js"},{"revision":"22b7a891e2c9d07844c6c7540461fd26","url":"assets/login.b2569b5e.js"},{"revision":"2ec90efeba5d62542f803f0a7fd0d32c","url":"assets/login.dc3182f4.js"},{"revision":"96ba9f520b5b0952c7a6d45758e09d0f","url":"assets/login.eec379cc.css"},{"revision":"69bf39aeefd3780d08cedc95368e5e65","url":"assets/LogoMozoOficial.0ad0d32e.css"},{"revision":"da741499baf194224ed9f386c1e22c1f","url":"assets/LogoMozoOficial.59d3300e.js"},{"revision":"09a786126efecb1ba27e8c89f8cbaaf3","url":"assets/LogoMozoOficial.5b8fc42e.js"},{"revision":"31c93323a12af9bd1a61b4b91e0a0360","url":"assets/LogoMozoOficial.aeb5e390.js"},{"revision":"d99321077e4e4773671445d7e862d3f3","url":"assets/LogoMozoOficial.bfb0229f.js"},{"revision":"b8b2aa747ff6bd143186499186073e92","url":"assets/LogoMozoOficial.e1925fc8.js"},{"revision":"8f42e91721a3fa4fab3495b7038c84d1","url":"assets/masterService.18c6d5eb.js"},{"revision":"9f883e9d9d48ab8b28e047a10568b4cc","url":"assets/masterService.34b643b5.js"},{"revision":"eec0c52b94c16c4f23e7073a3352feb3","url":"assets/masterService.3daa761b.js"},{"revision":"aceccbbdef01401f26570a77da079c96","url":"assets/masterService.4870541c.js"},{"revision":"2a2929973969333500aff27684560aa4","url":"assets/masterService.a210a3b8.js"},{"revision":"c466013974488276e2ec1080b9c70bbc","url":"assets/masterService.c3eb1ff6.js"},{"revision":"1b99124838dee7db2934a13e631531f9","url":"assets/masterService.e2218f63.js"},{"revision":"c72ee448b68639166265063ac51d6a37","url":"assets/masterService.f5028f32.js"},{"revision":"c386621ff077e1a9b1f8f20efcd8f6c8","url":"assets/MesaRestaurantPos.0998b49b.js"},{"revision":"e5d79af65217962d252c21b8ad3889ef","url":"assets/MesaRestaurantPos.e6bb1db9.css"},{"revision":"875e789aadb3bee1aa2fa2ed0d99bbee","url":"assets/mesas.038f7348.js"},{"revision":"0c2fe82dd94f57531b32e0450cfe728b","url":"assets/mesas.14585879.css"},{"revision":"388d02c8e5f4a6c9eee8bb4eb8db9cb6","url":"assets/mesas.1b7bd4ba.js"},{"revision":"482dc1d3efd21fbd395edba638a656b7","url":"assets/mesas.1c058cb2.js"},{"revision":"7f6de36a6e754459a59e5d3e90808837","url":"assets/mesas.3b36560b.js"},{"revision":"cfdc818269168bf758fd53184150f937","url":"assets/mesas.500aa307.js"},{"revision":"24b3d805162dc0587c9341b02944093c","url":"assets/mesas.5f2eb95b.js"},{"revision":"ee0d40a91eed57e082768e6ce489d8aa","url":"assets/mesas.682cfb0a.js"},{"revision":"079c56587339ef3768907f77a06a09b5","url":"assets/mesas.9631f968.js"},{"revision":"e2ae28224f600ea7cfbddb0152f781da","url":"assets/mesas.a1217be4.js"},{"revision":"203e5ea13823f8c57516efa48ee480c5","url":"assets/mesas.a25a4fc3.css"},{"revision":"6ce474c51c12f15e2bd50d4839733f46","url":"assets/mesas.b266d88f.js"},{"revision":"b0f8e8ab5b8af4286d87a7188a06dedb","url":"assets/mesas.bc4345d0.js"},{"revision":"573a9f21de2756f5ebc03e5a879b291f","url":"assets/mesas.c0a856e1.js"},{"revision":"b3bde413b42b7a1975691763c99ef560","url":"assets/mesas.c1ca1ab5.js"},{"revision":"0b42f926dbb940faac018afb7e4d4f05","url":"assets/mesas.c3fdd42c.js"},{"revision":"100bf4c0c6e80aa392ae54d3d00ef02e","url":"assets/mesas.c54a6817.css"},{"revision":"d699d46a50d787ce8291932a094cffa0","url":"assets/mesas.d5ae14c9.js"},{"revision":"ea3d5da9044c05f4b17c55caed42f647","url":"assets/mesas.db0f11b2.css"},{"revision":"42baaf71fabfaf5b532d49034e939c6b","url":"assets/mesas.e59a6800.css"},{"revision":"c78dd817dbc812465b9f4b0df19d046a","url":"assets/mesas.f76cc4db.js"},{"revision":"48ffb7270bd0a31b899c2acc0b372495","url":"assets/MoveAmbienteModal.1040ce73.js"},{"revision":"bbfe327e29c64382446d58fd2fa2b209","url":"assets/MoveAmbienteModal.38701b21.css"},{"revision":"ff96435e7289697f81e1a81b70392f9a","url":"assets/MoveAmbienteModal.3ac7fa15.css"},{"revision":"b72adc00a43b77f2956ebf87be535461","url":"assets/MoveAmbienteModal.46f8f2c0.js"},{"revision":"7be3facbfcfe907d4369123b40204e50","url":"assets/MoveAmbienteModal.5fb0e4a9.js"},{"revision":"a4f3bc0da3d9c53b132a25c7121e00a3","url":"assets/MoveAmbienteModal.7a404973.css"},{"revision":"fa0f41271330abad1b094c1e02e4a92e","url":"assets/MoveAmbienteModal.8e9a723e.js"},{"revision":"ef97b1bdfcf7a3ceacbd5743cf8b77b9","url":"assets/MoveAmbienteModal.aae0bd3b.js"},{"revision":"02fa21261f3ec01254c758e59fb52b39","url":"assets/MoveAmbienteModal.e22f5e5c.js"},{"revision":"bc4fbc7d86cd9acd4496ad7dcb33700c","url":"assets/MoveAmbienteModal.ed6cd058.js"},{"revision":"4a3b17726c6b5e213d383169f3b1b0b2","url":"assets/mozo.0451a4b9.js"},{"revision":"ba03286d789d9efb3afd33360dd88486","url":"assets/mozo.1e418c07.js"},{"revision":"35fe177737377e7d4fcfd28c136267c2","url":"assets/mozo.4dcc919d.css"},{"revision":"48ac9372a356647f990ed900f1ade4d5","url":"assets/mozo.65ef66ce.js"},{"revision":"709518f9c597f253594a5415c90b2916","url":"assets/mozo.6b378a9c.js"},{"revision":"c7dd8254866b3fb66511e3a244a31aa1","url":"assets/mozo.7f1e164c.css"},{"revision":"a542cbbbcabd40201281ccae3173b7b1","url":"assets/mozo.b8f2382d.js"},{"revision":"238ac79eb679fba9c45e45901eb89134","url":"assets/mozo.b96787ff.js"},{"revision":"32e7d88a0c35ed98e5795628c73d6bad","url":"assets/mozo.df10fbc5.js"},{"revision":"13da227af3c03602ee15caa14cd246c9","url":"assets/mozo.f5da76d6.js"},{"revision":"8b5a55a936ee37834eb7546002ca5206","url":"assets/multiselect.312cdae5.js"},{"revision":"bcbd4dfe9e3f886417f1d8045336d53d","url":"assets/multiselect.3391df59.js"},{"revision":"205899173826537f91ea2c068bf7f7c0","url":"assets/multiselect.37d18953.js"},{"revision":"308da5c730c75397e90f33e2b691e085","url":"assets/multiselect.46ddd5a4.js"},{"revision":"939c9c23e601b93501977ad015f6f7c0","url":"assets/multiselect.c030f992.js"},{"revision":"fcf8a72ae97c553ac59b2448c2090260","url":"assets/navbarLayoutState.407dc132.js"},{"revision":"f2daea6ecf06d1603364e9ae3aceb17b","url":"assets/navbarLayoutState.4a72f367.js"},{"revision":"e26daace86929c8f8a74b812066788c4","url":"assets/navbarLayoutState.6d298b7e.js"},{"revision":"60de2b3ef302352b626e4af421069d10","url":"assets/navbarLayoutState.906dd9ac.js"},{"revision":"67ec8bf32d766e59679e24271cfa5056","url":"assets/navbarLayoutState.b8c74aa1.js"},{"revision":"4f1bb16ddcd631fe068d6f79ea49ab37","url":"assets/navbarLayoutState.bf56cea2.js"},{"revision":"b15678e2e374957c31dd005e87fdfdd5","url":"assets/navbarLayoutState.e9420b5f.js"},{"revision":"84e3f8ba894417b65189304c90ef38c2","url":"assets/navbarLayoutState.f4be4684.js"},{"revision":"996d2dfe4ccffb37cdf4e34c26efe019","url":"assets/orders.12b1cc61.js"},{"revision":"e426a633c44d77d8156a5df590099557","url":"assets/orders.30a5adcc.js"},{"revision":"1d5fc0c2978a278d1b07194ab974a1e5","url":"assets/orders.457aa2e4.js"},{"revision":"c80aaa48e47c83ddb50d977228208cd5","url":"assets/orders.7541ca59.js"},{"revision":"d44125e9b5369f15c815fcb82141466c","url":"assets/orders.a8b47658.css"},{"revision":"aab6127138f86e7fe48f9e8a22660a24","url":"assets/orders.c9735aad.js"},{"revision":"0d889f0e3893a4949aa50e3c778badd5","url":"assets/orders.d21da02e.js"},{"revision":"d2daa6d5e415b1e81119350a01cf3842","url":"assets/orders.d6996429.js"},{"revision":"24615cff1b33013432589bb18cad4017","url":"assets/orders.e5bce046.js"},{"revision":"4d592a5fa242c2aa3349709d57980837","url":"assets/PedidoEnvironment.3813b644.js"},{"revision":"6adfa015c29b4772f5b0aab55ffd9541","url":"assets/PedidoEnvironment.43307d1c.js"},{"revision":"f9046cb2fb9c296cc553714bd7b61a23","url":"assets/PedidoEnvironment.60bff902.js"},{"revision":"bf9d53dfd746ad717d79bfe0c5bf6c60","url":"assets/PedidoEnvironment.8b9175bf.js"},{"revision":"ccef8eb0e92742417de3b0168bb6a75e","url":"assets/PedidoEnvironment.8faf1188.css"},{"revision":"14f0e6bf3cc395efb6cf792a24e16b3d","url":"assets/PedidoEnvironment.94251bed.js"},{"revision":"24c66024fc44fb65a117c9fbb7dd0780","url":"assets/PedidoEnvironment.a4175360.js"},{"revision":"c5eb6f9da5f00baf17e3a1c6d1579a91","url":"assets/PedidoEnvironment.b5659766.css"},{"revision":"f77a50274b29bddd057a57bd63248322","url":"assets/PedidoEnvironment.bdbdce51.js"},{"revision":"a9a5ad7973c1205adc2d911b51a91093","url":"assets/PedidoEnvironment.de1548f8.js"},{"revision":"7e68d416df65099cc42d106e286a02a2","url":"assets/PedidoEnvironment.e1f435ee.css"},{"revision":"4a61535315c8a3b85c9f563190daff42","url":"assets/plugin-vue_export-helper.5a098b48.js"},{"revision":"d7e31874ba97c2ebdb4d858237fe6834","url":"assets/pos.15818c01.js"},{"revision":"e268c003ef2d51b8661592b94e062e97","url":"assets/pos.2d51d14c.js"},{"revision":"b38d8e992493d01fa0633bea9fe17e64","url":"assets/pos.39592f95.js"},{"revision":"733d08eaa102baa2ebfee4119f0a4cd7","url":"assets/pos.3f985d2e.js"},{"revision":"c6bc74a4712d482dfb74166663b6da53","url":"assets/pos.407776e6.js"},{"revision":"1f225eac88f52847bfdb16b701a7dd11","url":"assets/pos.8d7ccb9b.js"},{"revision":"d2290abd19adbd7eedfa076edcbc163f","url":"assets/pos.927695fd.js"},{"revision":"1f421d86047acc64010ebee5ffbeebcd","url":"assets/pos.b0d12d1d.js"},{"revision":"738336af60a599ffe30506bedaf1bebf","url":"assets/pos.c1bb1f5c.js"},{"revision":"0832e282e00f736e6b92a3ca29702852","url":"assets/pos.e08cafb9.js"},{"revision":"4a84693f8d40568a717de5d482d1d336","url":"assets/prices.29f6692b.js"},{"revision":"203c02358e40346a228c9f5ff7511ec3","url":"assets/prices.2f9e6b2a.js"},{"revision":"a19e2c092516c47f3f6f3b7f6d78b14d","url":"assets/prices.315d1a8f.js"},{"revision":"47b4f8a6d8b4bc44ae4abebf78873648","url":"assets/prices.58886673.js"},{"revision":"81155c42fec628850c66b0b260b925a6","url":"assets/prices.676b9b4e.css"},{"revision":"a3eb6498d12b15a0b69762183ae13106","url":"assets/prices.7ffa1105.js"},{"revision":"6c612d22882c526d5e54130724e21446","url":"assets/prices.b7c15ea4.js"},{"revision":"36d2096d47c21294051d1fd08bb3c7c2","url":"assets/prices.cd6523ff.js"},{"revision":"8395578d323acae90464dca0f6188b3e","url":"assets/prices.ce469c46.js"},{"revision":"feeb026c94517b94948cdd9205eb32eb","url":"assets/ProductModifiersDialog.41eccd58.css"},{"revision":"ada27d37a31ea1bdef1d1f0b1442e2d4","url":"assets/ProductModifiersDialog.4340dfa2.js"},{"revision":"301ca4601d0768d6340d4bef7076e987","url":"assets/ProductModifiersDialog.4f25fc1a.css"},{"revision":"ea940fe02b5658650ff20e0442e99596","url":"assets/ProductModifiersDialog.aaf4855c.js"},{"revision":"9b3501452bc3b8854f8a5c9a22a571cb","url":"assets/ProductModifiersDialog.ce9208d0.css"},{"revision":"5b6624017300bacf0f8c788db612acf7","url":"assets/ProductModifiersDialog.f793dabe.css"},{"revision":"f17d31eb8f49e10d9d7a3b1d8f684521","url":"assets/purify.es.82af1ade.js"},{"revision":"0365affdd5af672e3c0cb1627af46090","url":"assets/realtime.05a535a8.js"},{"revision":"fb90a48d8868e4cbeb833ad35bd1d76f","url":"assets/realtime.0f302d8d.js"},{"revision":"ea236b6453fe71a51c831dac319e75d4","url":"assets/realtime.17f1e397.js"},{"revision":"cf70b01e97949955438a0101e84d5f59","url":"assets/realtime.4a9fec0b.js"},{"revision":"5bc37256b70085b9786ea8ba4971c10a","url":"assets/realtime.5af6cc83.js"},{"revision":"ceffe8f323741e98adfd29baed0b9344","url":"assets/realtime.ae8fa929.js"},{"revision":"b1f78fd37738eb799b66af696b74f5f0","url":"assets/realtime.f5941764.js"},{"revision":"235bf40408742bdfc4f2425a56853771","url":"assets/restaurantService.76a73e4a.js"},{"revision":"1b4d95a1773a945c481663ad95e9a573","url":"assets/sidebarLayoutState.3ce0e310.js"},{"revision":"b22ba6f3c94b3e5766a4f099817ec304","url":"assets/sidebarLayoutState.5ae46904.js"},{"revision":"ff7b152716ef33d967875990221bb0d7","url":"assets/sidebarLayoutState.95d1bd22.js"},{"revision":"ce8408079c3ef21e7e6ebfe9ee5a0991","url":"assets/sidebarLayoutState.ce1f1dd6.js"},{"revision":"aa9ee2cf8b1397437db43843a4ec3ed6","url":"assets/sidebarLayoutState.d444e432.js"},{"revision":"6add2c32571ddcc001736749fa48f801","url":"assets/signup.2e5730a5.js"},{"revision":"d6a8db1af22b02b0794ac01c7a3de82f","url":"assets/signup.713745bb.js"},{"revision":"e9ab0d29aa1c50c631b4d1e24bcb4112","url":"assets/signup.7cc26b92.js"},{"revision":"57ffeecb943a10e40ec905b5ce0c566d","url":"assets/signup.a029f3ce.js"},{"revision":"ba7ebe0b22e8f49e7e91ee4bf07a2084","url":"assets/signup.a65d6d3e.js"},{"revision":"cf1e23deab0af93969f2997d28f14da3","url":"assets/signup.c18b6367.js"},{"revision":"bc2f83fbb0c455b5f15ae9d56264f206","url":"assets/signup.f1d70b39.js"},{"revision":"1ac15434cded22954804058908b270a8","url":"assets/signup.ffa600a4.js"},{"revision":"3ba45bd36bc3796a19aee9b49ed8709a","url":"assets/slider.4ba41f4b.js"},{"revision":"a85b107d9a00575f1d61b7ffe43c7474","url":"assets/slider.af31fdd4.js"},{"revision":"edf194c19fca1cd05d1b24cc6d721d6b","url":"assets/slider.c405453e.js"},{"revision":"1686897859975782da89458b3274e10d","url":"assets/slider.eff6de73.js"},{"revision":"7217bfbfb81230246de4444cc69420b6","url":"assets/slider.f6a6eb1d.js"},{"revision":"0912bfcc0e234bba613e77d4c5e2d6ca","url":"assets/takeaway.03246dea.js"},{"revision":"cf04eeaa73f053151c5fa3a7c04b6041","url":"assets/takeaway.11bf8bca.js"},{"revision":"1798378891745c3c98393175f256d1b9","url":"assets/takeaway.7c034002.js"},{"revision":"f1c8bb08626a135b660b31725cb08b69","url":"assets/takeaway.8ca03060.css"},{"revision":"652bf39e6c39f3974c0eedc8dc85c9a2","url":"assets/takeaway.968c5ab1.css"},{"revision":"a5d11566d06b8541d8a5139137f53a62","url":"assets/takeaway.97ee705b.js"},{"revision":"27ae5c6ec0b9a4bbe34c659f137b4528","url":"assets/takeaway.9c5642bb.js"},{"revision":"51eec0b9d5dbce4872b3f7513640621c","url":"assets/takeaway.b40bacb9.js"},{"revision":"f5cf4509dc51c0259c879196a53774d6","url":"assets/takeaway.dcb9b3b5.js"},{"revision":"51ffe807450cda08b056b5f7023933d4","url":"assets/takeaway.e7cfee2d.js"},{"revision":"319e414ab76bce609563998f28001edc","url":"assets/takeaway.efc43d36.css"},{"revision":"56f4f55cb390f6e38796604c99a4e06e","url":"assets/takeaway.f382de4a.css"},{"revision":"a919e07cc439a2e336d14a0ae71f119a","url":"assets/tooltip.24128ff9.js"},{"revision":"ffe12a7d419803b2b156f7e47f0e9685","url":"assets/VAvatar.392df83f.js"},{"revision":"f2243a398ffb05a52206e7f8fe3e8480","url":"assets/VAvatar.4eca5934.js"},{"revision":"1fa6cd6268b0d3b48233a9a388cd73d9","url":"assets/VAvatar.8a46791f.js"},{"revision":"4d235c917271ec760a085c98e4a4a930","url":"assets/VAvatar.916bc669.js"},{"revision":"1d5796c992d1e485ae1b1066dde44d18","url":"assets/VAvatar.b01c944b.js"},{"revision":"fe02f01771586f8c7c0f325154992728","url":"assets/VButton.03b0164c.js"},{"revision":"1c4e1cb64d567905b307fb32031522ac","url":"assets/VButton.2bd31a3c.js"},{"revision":"20657e414decb65ee7447193cb79f2d9","url":"assets/VButton.2be3e296.js"},{"revision":"b6f6683fa6cfdae99012bfe2ebe14f7e","url":"assets/VButton.4bd674d0.css"},{"revision":"3ffd76b488c1effa0304e5167934b4c0","url":"assets/VButton.a329028a.js"},{"revision":"4443802dd30dd2d21590955e2958b165","url":"assets/VButton.e28c104e.css"},{"revision":"3943507e506865a28d89bd9339df71d8","url":"assets/VButton.fbae0555.js"},{"revision":"2501d16acc2af02eb4c24519b888dcba","url":"assets/VControl.243637c8.css"},{"revision":"73a3cdfcdb90feaaa13be1bc8a2b9224","url":"assets/VControl.3ac22e58.js"},{"revision":"04d77d2fd6679146bf7a6d6460983c8c","url":"assets/VControl.4be8848d.js"},{"revision":"6dba058cb39727aef325fc5ec6cbf879","url":"assets/VControl.5ab3a926.js"},{"revision":"553b96604a1bb3ec3ef6d7b517942fe1","url":"assets/VControl.66ed690d.css"},{"revision":"9af5629bf0718331e8b12adeb27f20a1","url":"assets/VControl.ab20f615.js"},{"revision":"f71aa95c419720334c4079740d7d7d14","url":"assets/VControl.c40603f4.js"},{"revision":"1336fa04f0a668ebbf99579181ce5974","url":"assets/VDropdown.0f83e5f1.css"},{"revision":"c7a408b13c993c88ab55b604dea96c1c","url":"assets/VDropdown.30a2a102.js"},{"revision":"6dd927fffadd1334001c2ab4a57469f8","url":"assets/VDropdown.612abd01.js"},{"revision":"b3996c5d7140d94b8287813266151251","url":"assets/VDropdown.6c1d270c.css"},{"revision":"14602c3b945a2d2a6d737d913e437252","url":"assets/VDropdown.76cd0ffc.js"},{"revision":"712e1e39fbaa418b5b5a633e4b0025e3","url":"assets/VDropdown.aa1f4c97.js"},{"revision":"5fe35cf154c0fec995121c9d337a3d24","url":"assets/VDropdown.d0ee03b8.js"},{"revision":"4fd3f55a3168da1ec7aaa792bc624d24","url":"assets/vendor.0611facb.js"},{"revision":"af88fb398e1cbf3d3bd4dc69b615a2d5","url":"assets/vendor.3c361039.js"},{"revision":"6d2f05bd04476b611a8fc4f1ae82ef76","url":"assets/vendor.47bbcd9c.js"},{"revision":"d8f25a08ccdbef56f0039accb36370d0","url":"assets/vendor.dca42141.js"},{"revision":"d1a984594ec06af09b4865cc4a03272b","url":"assets/vendor.f54a8f4c.js"},{"revision":"311879af293300443a4ca0ec447b315a","url":"assets/VField.04345baa.js"},{"revision":"f747c449cb12bdb62b81e836599b4aef","url":"assets/VField.06ac9a93.js"},{"revision":"f8f0247c8305139bafaefe6797e52b19","url":"assets/VField.0b883ad8.js"},{"revision":"2e63834988bec17b70fa7abd9ad5a438","url":"assets/VField.547aede3.js"},{"revision":"09931192bba7b2680ae091052ad3d0bd","url":"assets/VField.d83ee54f.js"},{"revision":"39f498ca4bebcdabbd6f37c8cb10c075","url":"assets/VIcon.394dd7c3.js"},{"revision":"9f9753902d0974f57a6aba92775c2f0e","url":"assets/VIcon.3f8eb681.js"},{"revision":"ca970cae7cacda0e07777a3a77bef033","url":"assets/VIcon.51cd8055.js"},{"revision":"f57d1d063c0bb8368489c0bb4a31ab08","url":"assets/VIcon.85ce7ff3.js"},{"revision":"bcafcacbc40554bbde5a83bbae8c49cb","url":"assets/VIcon.90bdc252.js"},{"revision":"aa398db56b2bb7c3e5b2360231f90d13","url":"assets/VIconButton.03fee79f.js"},{"revision":"ab57e8ae06d62a9f0c1a3812089e1ba8","url":"assets/VIconButton.043495e6.js"},{"revision":"580deb5ba95a89ce07b229d34e6322ff","url":"assets/VIconButton.53531b66.js"},{"revision":"4140cef38c28633cf33eba2995a805fc","url":"assets/VIconButton.856daf8c.js"},{"revision":"8ba5ab8183dc2d8b557e81739417eec1","url":"assets/VIconButton.c045eea9.js"},{"revision":"4f519ac4e8f2183d499b962e53561ff3","url":"assets/VLoader.0f2164f0.js"},{"revision":"3d222981b6348a3dc874689e12816373","url":"assets/VLoader.789890da.js"},{"revision":"e098ff50e9e29e0c734bf9686c73e4c8","url":"assets/VLoader.9ad9c176.js"},{"revision":"d568bf2621eca7ca3cc0b4dcd6cd70c2","url":"assets/VLoader.cc16436b.js"},{"revision":"4adb88941d10515f59a5d5b440db50bd","url":"assets/VLoader.d005b1d3.js"},{"revision":"3562de6ae52528a9e2fa86d5ebc5e2bd","url":"assets/VModal.634efbbb.js"},{"revision":"228c6a01de63a83a19dab83a09747425","url":"assets/VModal.95874e8d.js"},{"revision":"f92b6c0d3c2f6e85f267f4e386d6fda9","url":"assets/VModal.d10f8864.js"},{"revision":"fb46dd4319ae1410feb6ae1e79dc6429","url":"assets/VModal.d2e0a5eb.js"},{"revision":"2efa864dca2c26bfcaa1a4826eb9504f","url":"assets/VModal.d8de09e0.css"},{"revision":"e0b60acedaeba50841c107ee3b474888","url":"assets/VModal.fa3cd151.js"},{"revision":"d38eb729dbdd3e45bcca4ca81fa521ac","url":"assets/VPlaceloadWrap.0c90a0a2.js"},{"revision":"b67192fa1812461f80f488a4b68e0134","url":"assets/VPlaceloadWrap.4a1de6e8.js"},{"revision":"b08f4764640203de329bee8f3d69327b","url":"assets/VPlaceloadWrap.7a2b9b98.js"},{"revision":"23c87eea66464cd917a8dd0a29e24bef","url":"assets/VPlaceloadWrap.89d5fae9.js"},{"revision":"b15d0e62c2ec7a7fbf7494145b48bfba","url":"assets/VPlaceloadWrap.d6fb1891.js"},{"revision":"313b6564d572edb61d7dfd5e2be329f8","url":"assets/vue-tippy.esm-bundler.102701f8.js"},{"revision":"b213e6e940b6506547f821dc1ed11345","url":"assets/vue-tippy.esm-bundler.27a41289.js"},{"revision":"dc040c2206a2fc8a12c2d028172bfdc9","url":"assets/vue-tippy.esm-bundler.394f106d.js"},{"revision":"1ffd87fbdeda58953e0a6d244142a472","url":"assets/vue-tippy.esm-bundler.8fd45e2e.js"},{"revision":"925411a59dd0f61fa46d77d5080a1675","url":"assets/vue-tippy.esm-bundler.ab0ac7d6.js"},{"revision":"140ec737751ebb70cb006fc08849095b","url":"index.html"},{"revision":"d10bf770c774886de92bf247c64e674b","url":"vendors/font-awesome-v5.css"},{"revision":"4f9e480835662341f352d3262e6baff2","url":"vendors/line-icons-pro.css"},{"revision":"46c80fa08da7df61e0300ce3697ac93f","url":"vendors/loader.js"},{"revision":"eda6bc09b35dd6194bd89102886f68f2","url":"vendors/prism-coldark-cold.css"},{"revision":"aa8095a9d6bd9466bfdee6a9e0fb143e","url":"favicon.svg"},{"revision":"2608995d3ce047aed1b4f12314b971e6","url":"favicon.ico"},{"revision":"cd9cd94aaa699e0a16e692b6bb16f672","url":"robots.txt"},{"revision":"b1fc7ba21cbe0c252ddf4e374dff5bcf","url":"apple-touch-icon.png"},{"revision":"598ac9f6ba4777c6a0839a61f484cc95","url":"pwa-192x192.png"},{"revision":"fdde4a327d6c825b405236efbb8da6e3","url":"pwa-512x512.png"},{"revision":"d53355ae7cfa9a3d615079c35d1770e5","url":"manifest.webmanifest"}]);
registerRoute(({ url }) => url.href.startsWith("https"), new NetworkFirst());
