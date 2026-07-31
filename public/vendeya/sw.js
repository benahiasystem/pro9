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
precacheAndRoute([{"revision":"2dd8231b1b3de91c911896628718c4c1","url":"assets/[...all].54eb575a.js"},{"revision":"33b2411a95bf7ebe212a66b7124dd397","url":"assets/[...all].cd05e41e.css"},{"revision":"868b5c523d984923619ef952ac7507da","url":"assets/app.00277235.js"},{"revision":"aae4f4321e154a92c6fe4e913f8df31b","url":"assets/app.1e91e04c.js"},{"revision":"040ce7156a605e6159faad83275c5faa","url":"assets/app.581ef642.js"},{"revision":"4cdb00e3f530256da8015f2804fce7d1","url":"assets/app.5cd6040e.js"},{"revision":"8a18d207dde32e307987f674ae0d5811","url":"assets/app.616ead18.js"},{"revision":"35f25a8eff89d3ef16d3a2502ada22a8","url":"assets/app.7f0f1e86.js"},{"revision":"148719c6b6804bcfea02e6b7b3952874","url":"assets/app.8e1f5942.js"},{"revision":"4cefe0e6605af2f87b4b9788a33d7750","url":"assets/app.99946d35.js"},{"revision":"2fa63e87e6c3b9e971e24afa7c8e6f1f","url":"assets/app.a70b81af.js"},{"revision":"51b13faad7829a22ec315f219a60e870","url":"assets/app.f37db8c4.js"},{"revision":"29f93d4fc516f147d9dd7e13bd28c438","url":"assets/app.fc63dd75.js"},{"revision":"90b9dccde2b1d4aaabd544bf0d8c2ab9","url":"assets/AppLayout.1f582ba0.js"},{"revision":"f02e7f7a33031943a9eba016c9a9257a","url":"assets/AppLayout.285bcb37.js"},{"revision":"6fe7f84daeb67b7bcf7a2fac3ebc927b","url":"assets/AppLayout.363bcda7.js"},{"revision":"59733c5e21a8adb31f49ce0dcf1116f3","url":"assets/AppLayout.42164713.js"},{"revision":"96f7e2bdb55e01b978ace2023ad61157","url":"assets/AppLayout.49174f49.js"},{"revision":"c7d077be6e5dff85b8db263940a4f982","url":"assets/AppLayout.79625695.js"},{"revision":"ba5dc51eea7300458a163212ce331136","url":"assets/AppLayout.86d92d54.css"},{"revision":"855d2b0ff319cefd5d4410d999772b98","url":"assets/AppLayout.a04c0a7f.js"},{"revision":"dc4866e6c8831350a8449f9588da3dca","url":"assets/AppLayout.a1370268.js"},{"revision":"5ad4b4252e7f1b44a22f00b2e63ab725","url":"assets/AppLayout.ac44e6f5.js"},{"revision":"1df0ab3a3748b8f8113e3e61e0db74a4","url":"assets/AppLayout.c46277b2.js"},{"revision":"3cecb1480c6a8390fc2616a5bf6d9eb3","url":"assets/AppLayout.f1c5c59a.js"},{"revision":"08a0cc320d7cc687ba079cf60ec3a330","url":"assets/auth.120235ff.js"},{"revision":"49e3dad0d2f436723b49030e40acf48f","url":"assets/auth.7dbfcfa4.css"},{"revision":"efdc6a8f238eb52bad95db87a24f3637","url":"assets/background.5bd78b71.js"},{"revision":"72e78068f7ff0648792ce2825b3b425b","url":"assets/has-nested-router-link.4fe8dab1.js"},{"revision":"b5ae9df6887dfe61fa21924638ef8e26","url":"assets/index.0798299b.js"},{"revision":"5149f99378fb3c3ef36cdb28ef695471","url":"assets/index.29dfa54e.js"},{"revision":"a7f30504fc21b7504f62c39f4b2355ea","url":"assets/index.2aa79e74.js"},{"revision":"36df8d69efae8e67f68b5fd084da7f43","url":"assets/index.34e397f1.js"},{"revision":"da1a4c320637a121337a9d34474519d5","url":"assets/index.3761037c.js"},{"revision":"f76801595d14b40dfe4da8e4dc88d6f7","url":"assets/index.396e79a6.js"},{"revision":"dbddaf31193a8a58c3cd826f608696a2","url":"assets/index.3a773319.js"},{"revision":"106186b900bac7583cfb1a4564de2bc3","url":"assets/index.3bb13d9e.js"},{"revision":"30f260dbd8ad907a5cc0b70dd0cd44fe","url":"assets/index.4ff7ee6c.js"},{"revision":"fec055857d9cb8680cb9623d759605fc","url":"assets/index.50c8c140.css"},{"revision":"616c98e400f71649b37b8be8188a93d7","url":"assets/index.688eb4fa.js"},{"revision":"ea8e5e9d28590b4c5e54db5b12303b33","url":"assets/index.6a4a49e2.js"},{"revision":"13973aec0b93da4addf79bc80736cd16","url":"assets/index.6c15c365.css"},{"revision":"9c3848ae08f33728317181c145b4cd01","url":"assets/index.76a52da4.js"},{"revision":"4e43f839300b000fe54f71db4ae2203e","url":"assets/index.82f614ee.js"},{"revision":"314f1496fd10c970d8a026af4f5a5d26","url":"assets/index.87bb5625.js"},{"revision":"f50944a45b7dbd5c2f5f3cf9ae4b22d2","url":"assets/index.8df8a42b.js"},{"revision":"40a77b98a8b15ce1ea3e765cb60ce76d","url":"assets/index.968ad865.js"},{"revision":"b7acacfa4a737c6bf38f8e5b3277f6ce","url":"assets/index.9c65d150.js"},{"revision":"c101f746fa9439e9ac3578aced5abc33","url":"assets/index.9d4978a1.js"},{"revision":"c61afb9fec2431ec1f533991bcce1191","url":"assets/index.a785ec46.js"},{"revision":"dca320f2cf04d3c6d89d22e530cf5d95","url":"assets/index.aa5e7121.css"},{"revision":"93f65ff76638bb8124911a916b02a318","url":"assets/index.c542e05a.js"},{"revision":"80dd438049c6fd6621cb19336bf90da8","url":"assets/index.d5ede680.js"},{"revision":"c0649250c5818e110d9bd8c9063762f6","url":"assets/index.e266ca3b.js"},{"revision":"1c4c566e9a7c3839320ec57347c3fa32","url":"assets/index.eb9630d3.js"},{"revision":"ea1d0f9c50cb347e7327b9e3142f6369","url":"assets/index.fe372547.js"},{"revision":"570831909cc963af5ff4a81661fff251","url":"assets/IsotipoMozoOficial.521b98ca.js"},{"revision":"c7433bc9cafa8e24975460e35eb49e19","url":"assets/IsotipoMozoOficial.9f9a51a9.js"},{"revision":"69caed11b99350c69368155c85c4f30c","url":"assets/IsotipoMozoOficial.a97af8de.css"},{"revision":"93e87697c6cb9c303dcc45f63c7f6121","url":"assets/login.04aaa21b.js"},{"revision":"9390c2deb6ebdee90804fae01df4c127","url":"assets/login.071be3c1.js"},{"revision":"db30365dd90d0494789f90bf6a06d2d5","url":"assets/login.0e4a3684.js"},{"revision":"e5ad7748e9aba10c520530d62516224a","url":"assets/login.1920651b.js"},{"revision":"ac85d9c627ee34fb2120cd83a473df69","url":"assets/login.2284878c.js"},{"revision":"1d6f1a8808b28a57da6fdba6e589c904","url":"assets/login.2a406c43.js"},{"revision":"32a1f455e92e39acff84ac4dbc7c31ec","url":"assets/login.672e669d.js"},{"revision":"4b88615b8bfe25e2806b4553298e1fe4","url":"assets/login.848618dd.css"},{"revision":"d47ae05a63dd179101f52ec14aeef12b","url":"assets/login.a316490b.js"},{"revision":"3c6bb9a4f7a87bdd6be742949174d153","url":"assets/login.c806b10c.js"},{"revision":"d3fd365e59b59781241c2a024902ac3d","url":"assets/login.e4ae9fd7.js"},{"revision":"fff80686c0d02d484449b5e47dc97da5","url":"assets/login.fe11595c.js"},{"revision":"c51c31f76d757ed87d85bc3c5c88707e","url":"assets/LogoMozoOficial.7307420e.js"},{"revision":"9861c15366e90415164b4a6b7b678b6d","url":"assets/LogoMozoOficial.a446dab9.css"},{"revision":"9e01eda6ad5fe3127b9f40d2f976d400","url":"assets/LogoMozoOficial.df30c763.js"},{"revision":"d2e1f8d8e8590427af0202afa11bb848","url":"assets/masterService.1117a1ab.js"},{"revision":"77244646c91607a5fa264408587363fd","url":"assets/masterService.11b53c12.js"},{"revision":"00dddc611e178b509162ce03baa57196","url":"assets/masterService.252e633b.js"},{"revision":"e9bd2290635a2be8792eaff09b30c85b","url":"assets/masterService.499570ad.js"},{"revision":"de708884a83bedb57289be301330c8e4","url":"assets/masterService.a5d4b26f.js"},{"revision":"ec8c77f89882c93c2f8af0ea292c18e4","url":"assets/masterService.b4ed7875.js"},{"revision":"82f162121455b5235bf7fcfaa030bae9","url":"assets/masterService.bfe8f946.js"},{"revision":"484406b12d067a3c990426f1e34591ca","url":"assets/masterService.c89066ae.js"},{"revision":"d6bf70c9510042c5a47f1943ccf80449","url":"assets/masterService.d8e3f1fd.js"},{"revision":"3003873b6e5fe0d0b9188fa056894fe7","url":"assets/masterService.e05a4bf0.js"},{"revision":"e8cb52c14869749e74204992200812f9","url":"assets/masterService.fa09b494.js"},{"revision":"bf8ba3b71ff1d7c1f9595dfbc8b56224","url":"assets/mesas.22a37aed.js"},{"revision":"d96cdb2e5bf2b27677148a6313f04802","url":"assets/mesas.2b71883e.js"},{"revision":"b311c20329aafebe9fa80098ebb09977","url":"assets/mesas.4fc0ce90.js"},{"revision":"888dd508a62564454c899f2b17f91db2","url":"assets/mesas.5e55e56b.js"},{"revision":"471ffd16a21c57bbf5b265cdce83dbc6","url":"assets/mesas.6dea1ddf.js"},{"revision":"c0fc263c88359d58729d2527796b8728","url":"assets/mesas.6ee355d6.js"},{"revision":"d89f13cc8960b1e087bd893834f9bac2","url":"assets/mesas.acccb04f.js"},{"revision":"799bb3fe18928a7906ad68a6aeffac0f","url":"assets/mesas.b09ba7d2.js"},{"revision":"12445455c82f3503ab31f688bf85c422","url":"assets/mesas.b31de8e6.js"},{"revision":"c59251f91e4cd9f2953656f2d55b6d40","url":"assets/mesas.bfcda7c9.js"},{"revision":"7437f4e89511b5e634013fe307732aaf","url":"assets/mesas.d499cd70.js"},{"revision":"b92ae26e74f06837026a87d92a268b5f","url":"assets/multiselect.9fa31e2b.js"},{"revision":"4a61535315c8a3b85c9f563190daff42","url":"assets/plugin-vue_export-helper.5a098b48.js"},{"revision":"4f44251b8eea3840cab96e13cf58141d","url":"assets/pos.70521939.js"},{"revision":"a00272c8ed69d423b297cf0ae4fba113","url":"assets/pos.ee45123d.js"},{"revision":"4ab543a0ab94b6d386bf9e26e88d9215","url":"assets/prices.07c7e733.js"},{"revision":"248f03f8d04cff93b40ac43dac5a6d65","url":"assets/prices.10fab5ed.js"},{"revision":"b82150df58208e045d60933935c63467","url":"assets/prices.17cf2394.js"},{"revision":"10dcf22f45607f63b4fa8cc7a053114b","url":"assets/prices.418d5bce.js"},{"revision":"4f2de68d03c3ed2e18e1f8c073e04466","url":"assets/prices.454d4dd8.css"},{"revision":"93e0a2b0fe96c1412cef769ea013bf36","url":"assets/prices.51c4ce9b.js"},{"revision":"f96b1170f1b84900be5399d18e0c9838","url":"assets/prices.5dcb0ae8.js"},{"revision":"9502a3d851777570273611db370ccdee","url":"assets/prices.8c7e1b73.js"},{"revision":"70f5773737a1820df20d79b0e6555dee","url":"assets/prices.8f20bdd0.js"},{"revision":"a5efb630739a946c612994cc6873dfcc","url":"assets/prices.910a0bee.js"},{"revision":"2cd8b4ff6edb301c7bdfabbd41cb456b","url":"assets/prices.aa4d7e4f.js"},{"revision":"61399e614da17678153acb104be68b96","url":"assets/prices.e97a322f.js"},{"revision":"11c04094ae837ae939b026478f5d6e7b","url":"assets/sidebarLayoutState.19309e72.js"},{"revision":"eb22aecd16e42aef7254327906df7d9e","url":"assets/signup.11d7e12e.js"},{"revision":"d01833ea3c5f3a2b5eed20c53e0c8bc6","url":"assets/signup.2201b573.js"},{"revision":"46e087b3a8512ee9904a7707b76c92bd","url":"assets/signup.31bfe38d.js"},{"revision":"604ee5fd62a9737b7d60fa4d75b34331","url":"assets/signup.3f2ecebf.js"},{"revision":"96ba9f520b5b0952c7a6d45758e09d0f","url":"assets/signup.4b6fdd91.css"},{"revision":"c2e9da1209ff3578057b44ed60a48bda","url":"assets/signup.5b1852e1.js"},{"revision":"62518af1ac0412acd5dddfcacbcffe7b","url":"assets/signup.66459859.js"},{"revision":"d22e42dcc2da93510e513fa72eb9f4b4","url":"assets/signup.8adb7ba3.js"},{"revision":"4737239d3fb2655435f11178c22bff75","url":"assets/signup.90c52744.js"},{"revision":"94bdab6e54ac714b19a159fb1b8fcd13","url":"assets/signup.d6872b98.js"},{"revision":"a0f81d825158dd531ab791ea5d1adabf","url":"assets/signup.e280b82f.js"},{"revision":"054657c184dc8b120b2810837d9b0972","url":"assets/signup.eed567fb.js"},{"revision":"2d069a8f40350aef0ca45103d8662913","url":"assets/slider.66005391.js"},{"revision":"a919e07cc439a2e336d14a0ae71f119a","url":"assets/tooltip.24128ff9.js"},{"revision":"9e85d106fcc42963b37c9f58137266c3","url":"assets/VButton.0d870fba.js"},{"revision":"4443802dd30dd2d21590955e2958b165","url":"assets/VButton.e28c104e.css"},{"revision":"1ac57c116b32a82f128440f28fe90518","url":"assets/VControl.243637c8.css"},{"revision":"7cbf6d5575240d18f109593c3ef507da","url":"assets/VControl.8f7a9833.js"},{"revision":"2d58c064d03365d85b00c0559c4e5a2f","url":"assets/VDropdown.00cd1170.js"},{"revision":"3e50a812f655461339a81c9afd7516da","url":"assets/VDropdown.79a9bddc.css"},{"revision":"56f52929550a6a38873721e0f6f53888","url":"assets/vendor.73f133b9.js"},{"revision":"99aeca4684f8d6a0df93a24d7c637d48","url":"assets/VField.cf44fb41.js"},{"revision":"a29ce7dd52d2febc5e1018920afef604","url":"assets/VIconButton.038cef8e.js"},{"revision":"4ce6e3d704e756ed214552eb707c0643","url":"assets/VIconButton.04ed91bf.js"},{"revision":"21e8cefbe3ea95b63debc7baa26a199d","url":"assets/VIconButton.1347f85f.js"},{"revision":"7603924e436495530ee903ae0f899a97","url":"assets/VIconButton.260c15dd.js"},{"revision":"bc559c4fe2c56ad47401ab16cb64d54f","url":"assets/VIconButton.3be2944f.js"},{"revision":"5c562322a9a17b809e56a0e66a9bc28d","url":"assets/VIconButton.41b04720.js"},{"revision":"944df3d6a2b0f856febed1f7d24f364a","url":"assets/VIconButton.8ad05465.js"},{"revision":"b3d413ebba9774e9f7f5eb8eec1badf3","url":"assets/VIconButton.8fd84b32.js"},{"revision":"97ef10f768cf80a7f8a4b64b83cd194a","url":"assets/VIconButton.9b63f1a5.js"},{"revision":"6eba540ba8cd9722fddbb1eaf7184151","url":"assets/VIconButton.c61903f8.js"},{"revision":"14c9addeca409b6230b4e4f2c59d7292","url":"assets/VIconButton.e8d7e7a2.js"},{"revision":"f1fbc72c04c7de9c6defb7521f417d6a","url":"assets/VModal.45cd9038.js"},{"revision":"2efa864dca2c26bfcaa1a4826eb9504f","url":"assets/VModal.d8de09e0.css"},{"revision":"bdc55d7958a52e82ed94d16e8fbc4c39","url":"assets/VModal.faedfed7.js"},{"revision":"b6699993274f319fa597b864350e09a6","url":"assets/vue-tippy.esm-bundler.2ffba17f.js"},{"revision":"3fead54dde459299172278b54eefed71","url":"index.html"},{"revision":"4c8b74382b4f6b2cf5f8afcb87e80abc","url":"vendors/font-awesome-v5.css"},{"revision":"4bb4c5797d6ce8bd02b13e2d12c34bcd","url":"vendors/line-icons-pro.css"},{"revision":"84dcb5fdcc61a1daadf6607b40bd09ed","url":"vendors/loader.js"},{"revision":"238822f024eb9bd172d4d6494cacd69c","url":"vendors/prism-coldark-cold.css"},{"revision":"3dcac5b40fced888f5563eaf6521c1cd","url":"favicon.svg"},{"revision":"2608995d3ce047aed1b4f12314b971e6","url":"favicon.ico"},{"revision":"f77c87f977e0fcce05a6df46c885a129","url":"robots.txt"},{"revision":"b1fc7ba21cbe0c252ddf4e374dff5bcf","url":"apple-touch-icon.png"},{"revision":"598ac9f6ba4777c6a0839a61f484cc95","url":"pwa-192x192.png"},{"revision":"fdde4a327d6c825b405236efbb8da6e3","url":"pwa-512x512.png"},{"revision":"01f69260a10db7804bff725438665e08","url":"manifest.webmanifest"}]);
registerRoute(({ url }) => url.href.startsWith("https"), new NetworkFirst());
