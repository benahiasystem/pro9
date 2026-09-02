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
precacheAndRoute([{"revision":"0590dc67469b9852750e690ae0a7e6d6","url":"assets/[...all].16f3dabf.js"},{"revision":"2dd8231b1b3de91c911896628718c4c1","url":"assets/[...all].54eb575a.js"},{"revision":"33b2411a95bf7ebe212a66b7124dd397","url":"assets/[...all].cd05e41e.css"},{"revision":"f892700cb2a8def3ab0f8438f1a4ed87","url":"assets/[...all].fadeb723.js"},{"revision":"fe2f9acb5053ef13df828830122363de","url":"assets/app.2b1bcbc7.js"},{"revision":"db4e178c68dabe539d89c47df0eb6a3c","url":"assets/app.457d51b3.js"},{"revision":"beab1663166511603e263db419402fd2","url":"assets/app.49675155.js"},{"revision":"64c8fcbe4dc085892424fcba79f41ee7","url":"assets/app.740e17d3.js"},{"revision":"9644fec3860afcf4b80be73b53c8fdec","url":"assets/app.e6f907ef.js"},{"revision":"007db93ffa2a0faef09d1f10568cac84","url":"assets/app.f2a637db.js"},{"revision":"39e3330d4571b3c4d8d8645ff67eb744","url":"assets/AppLayout.2dc61bcd.js"},{"revision":"c87b79f2986792836b74812b71caed8b","url":"assets/AppLayout.51e68d7d.js"},{"revision":"7a0a6fe1adef1e4dc65651e59dc717c2","url":"assets/AppLayout.5dbeb306.js"},{"revision":"ba5dc51eea7300458a163212ce331136","url":"assets/AppLayout.86d92d54.css"},{"revision":"3b9f849510359e15552698c8d6e0ce1c","url":"assets/AppLayout.8f5ee939.css"},{"revision":"d71d8db73543e4adb1751e1574732c72","url":"assets/AppLayout.93ce8665.js"},{"revision":"eb3fe545b07822d86973909e3d9b1a30","url":"assets/AppLayout.a3045291.js"},{"revision":"81bb8a899dc322d338a301e985b871a8","url":"assets/AppLayout.ff1af636.js"},{"revision":"08a0cc320d7cc687ba079cf60ec3a330","url":"assets/auth.120235ff.js"},{"revision":"651fc4cb86eaf975297a3ecd7a9aed3c","url":"assets/auth.6bb8b14f.js"},{"revision":"448bc0c48f65fc4f6f6dd698b7531374","url":"assets/auth.735db506.js"},{"revision":"49e3dad0d2f436723b49030e40acf48f","url":"assets/auth.7dbfcfa4.css"},{"revision":"efdc6a8f238eb52bad95db87a24f3637","url":"assets/background.5bd78b71.js"},{"revision":"72e78068f7ff0648792ce2825b3b425b","url":"assets/has-nested-router-link.4fe8dab1.js"},{"revision":"86d6a2da5a1bf4e0060feb1fe3f9a46c","url":"assets/index.0b01144c.js"},{"revision":"21fab9a7b71cccdd76e42339972ebf6c","url":"assets/index.0d1fe543.css"},{"revision":"2afa2f36259fff8c060ffbe0fdeca212","url":"assets/index.107fba77.css"},{"revision":"7171fb223d112def0ea63a2809a9d2ec","url":"assets/index.11ed336d.js"},{"revision":"05273732161fd8e145a95cbc2d09f084","url":"assets/index.126ef6bb.js"},{"revision":"b92ad0274c6bbd738fd04034d1981e7d","url":"assets/index.178eaf97.css"},{"revision":"b53b94750c0de5fc8e9e02ed6be43048","url":"assets/index.1f531e39.js"},{"revision":"daae0c6a3eceb48eec07e938aca3a0ad","url":"assets/index.21fb6a7e.js"},{"revision":"36df8d69efae8e67f68b5fd084da7f43","url":"assets/index.34e397f1.js"},{"revision":"23d8064ace5c1f319df1b19bc5b09592","url":"assets/index.42a48c3f.js"},{"revision":"381b43a82ddd7a6551d604f8ee5e98ba","url":"assets/index.48784e91.js"},{"revision":"21fab9a7b71cccdd76e42339972ebf6c","url":"assets/index.5ae32848.css"},{"revision":"c53c0f6a707c047906ba581f3bf294a8","url":"assets/index.5ae96c02.css"},{"revision":"f50944a45b7dbd5c2f5f3cf9ae4b22d2","url":"assets/index.8df8a42b.js"},{"revision":"78a94378881bb19150ea834b55878f68","url":"assets/index.96c67a2c.js"},{"revision":"afd623ab8c3405de0cf99824e59d6984","url":"assets/index.b16d6934.js"},{"revision":"dde4ead4bdda9964bf72577c5d1f6edf","url":"assets/index.c2b1f471.js"},{"revision":"a5ab67a30347438b4a742c78d73ed536","url":"assets/index.c6778608.js"},{"revision":"42196e18afa885ff9632edfed9c604c1","url":"assets/index.dd916fd1.js"},{"revision":"1c4c566e9a7c3839320ec57347c3fa32","url":"assets/index.eb9630d3.js"},{"revision":"b28a6293f93b798e48f7c910aabb06df","url":"assets/index.f7333f6f.js"},{"revision":"cec06341a4a25575584ae033cd258ae3","url":"assets/index.f8e5d429.css"},{"revision":"c9b764451772437baa7a6c7f4a4aeb93","url":"assets/index.f96dc89e.js"},{"revision":"9a3c3c0c351fbda92a0f790b0346f330","url":"assets/IsotipoMozoOficial.006c1e5c.js"},{"revision":"85a017c3674941957faa8ef27dc1a628","url":"assets/IsotipoMozoOficial.2a73f5e0.css"},{"revision":"570831909cc963af5ff4a81661fff251","url":"assets/IsotipoMozoOficial.521b98ca.js"},{"revision":"57114cf8a91e8ffe449396e650098498","url":"assets/IsotipoMozoOficial.a1a7fcdc.js"},{"revision":"69caed11b99350c69368155c85c4f30c","url":"assets/IsotipoMozoOficial.a97af8de.css"},{"revision":"7213a95721f1c3c6fd4fdf0a5f953467","url":"assets/login.0413995d.js"},{"revision":"be4a0e67bccbcc197f4fb9b9b6d12653","url":"assets/login.2314c72e.js"},{"revision":"6fdca5cef65c5353b7e8e9af97e6e451","url":"assets/login.5c71b631.js"},{"revision":"4b88615b8bfe25e2806b4553298e1fe4","url":"assets/login.848618dd.css"},{"revision":"7a54cc095da04ca211eafea23b2ea7e3","url":"assets/login.9b0fdd46.js"},{"revision":"b339618e033f5df62bb5d5e2d14b78b6","url":"assets/login.c4792c2a.js"},{"revision":"d8b4cec33a5487c8a6dbed4e5d2f4b56","url":"assets/login.e8eab9bc.js"},{"revision":"c51c31f76d757ed87d85bc3c5c88707e","url":"assets/LogoMozoOficial.7307420e.js"},{"revision":"089ce8ed8cac6946afb71ac5ac7c8b7c","url":"assets/LogoMozoOficial.893daed7.js"},{"revision":"9861c15366e90415164b4a6b7b678b6d","url":"assets/LogoMozoOficial.a446dab9.css"},{"revision":"c79e7226e311535d1345e8346f6dfe6e","url":"assets/LogoMozoOficial.da1efc38.js"},{"revision":"56fd36de116d9c3d9dd175d147925c5b","url":"assets/LogoMozoOficial.fc4913e9.css"},{"revision":"80bc09603352b0305db92e9251ab3200","url":"assets/masterService.82eb3f81.js"},{"revision":"5d03b457998a0c15c3d801348ab2e81b","url":"assets/masterService.9853fa55.js"},{"revision":"217d4023b81c8b173cf5ee8c263d9cb3","url":"assets/masterService.bda2fa25.js"},{"revision":"b29970532e43f024bfe78381e623029b","url":"assets/masterService.c098a128.js"},{"revision":"319d62ff5a899da69b69d9d8789e4d29","url":"assets/masterService.c18f9f8c.js"},{"revision":"528b1b0cbd4108ed1b68a4cfae0c1f04","url":"assets/masterService.c29ef153.js"},{"revision":"f23f6f341f862ccf81ada839c001edd2","url":"assets/mesas.317b6b38.js"},{"revision":"57f706fb83741d640ffae04867b9437d","url":"assets/mesas.85151221.js"},{"revision":"edc4f00747aa4f81924f86803003df6a","url":"assets/mesas.b408c98a.js"},{"revision":"590c1dfaa9295a84b64d2afed81768ec","url":"assets/mesas.cdf07be6.js"},{"revision":"c343bda6695c92e939d743a75e65e559","url":"assets/mesas.e5e12cde.js"},{"revision":"5828584bd426e11177470083b881b5f5","url":"assets/mesas.f823dfa8.js"},{"revision":"b92ae26e74f06837026a87d92a268b5f","url":"assets/multiselect.9fa31e2b.js"},{"revision":"db5987e4dd0ba1c9e023e41aa32678b6","url":"assets/multiselect.de958972.js"},{"revision":"31f4294ea0d037afa1a9ec71c5cb0b35","url":"assets/multiselect.ec027862.js"},{"revision":"4a61535315c8a3b85c9f563190daff42","url":"assets/plugin-vue_export-helper.5a098b48.js"},{"revision":"588321278b2149a5c1be565a6a03af3b","url":"assets/pos.090d63ac.js"},{"revision":"03e77bd1e4337225b144b5732cb90484","url":"assets/pos.4aadaa8d.js"},{"revision":"4f44251b8eea3840cab96e13cf58141d","url":"assets/pos.70521939.js"},{"revision":"eaf075ec8491cb95aae6d192182809be","url":"assets/pos.a287a48d.js"},{"revision":"b833fecf0096afc44e7b944291443b5d","url":"assets/pos.d22f1545.js"},{"revision":"a00272c8ed69d423b297cf0ae4fba113","url":"assets/pos.ee45123d.js"},{"revision":"4f2de68d03c3ed2e18e1f8c073e04466","url":"assets/prices.454d4dd8.css"},{"revision":"9999da74bcf3d3c10c69dccf2aea911e","url":"assets/prices.596d649c.js"},{"revision":"d337d4cfa0e8bd30a27d92408dede4f8","url":"assets/prices.874778de.js"},{"revision":"92786506de2fb43d5e1c9c204ecafde5","url":"assets/prices.a4374a54.js"},{"revision":"0782fcb8d4bc19dfabac4a4a49327929","url":"assets/prices.d28fa1a0.js"},{"revision":"e7e03b11c5312c5581a06c00f70a2d8d","url":"assets/prices.f212ba50.js"},{"revision":"fc1ace0be786562bb01d3319510eefaf","url":"assets/prices.feeccf97.js"},{"revision":"11c04094ae837ae939b026478f5d6e7b","url":"assets/sidebarLayoutState.19309e72.js"},{"revision":"09a9178cf16a1e73496d81e0b89ce11f","url":"assets/sidebarLayoutState.2efc371d.js"},{"revision":"59eff9f72d2c2c8ebda0c2730e743306","url":"assets/sidebarLayoutState.73aeb9c6.js"},{"revision":"a1eed228130fe683c78ccf90dcafd70a","url":"assets/signup.229bbbf2.js"},{"revision":"96ba9f520b5b0952c7a6d45758e09d0f","url":"assets/signup.4b6fdd91.css"},{"revision":"474f3198da1e118b7c53f405e57b5529","url":"assets/signup.8e3e31af.js"},{"revision":"3331100db470eb71f7453947b2bc0cdb","url":"assets/signup.a671ec8e.js"},{"revision":"8c13fbf865fec9a6e353c52ceb1f62a2","url":"assets/signup.c8a6c443.js"},{"revision":"4ccb1f4a66b14f899fae668b8265394c","url":"assets/signup.e0542e4c.js"},{"revision":"e13fcde0fdcc4fa9c13c31e7c104a612","url":"assets/signup.ff41e822.js"},{"revision":"d11a05c35c2a062f6bd4297fcd15ae3c","url":"assets/slider.0f43551d.js"},{"revision":"2d069a8f40350aef0ca45103d8662913","url":"assets/slider.66005391.js"},{"revision":"50d773582b720bb4c9408fb0ae335c23","url":"assets/slider.fb269de9.js"},{"revision":"a919e07cc439a2e336d14a0ae71f119a","url":"assets/tooltip.24128ff9.js"},{"revision":"9e85d106fcc42963b37c9f58137266c3","url":"assets/VButton.0d870fba.js"},{"revision":"b6f6683fa6cfdae99012bfe2ebe14f7e","url":"assets/VButton.4bd674d0.css"},{"revision":"7cfbbc27d6739163f2ff658d87daad44","url":"assets/VButton.74b292a0.js"},{"revision":"4443802dd30dd2d21590955e2958b165","url":"assets/VButton.e28c104e.css"},{"revision":"3d0333e4ed2d42d6f692eb8ca8eff2cd","url":"assets/VButton.e2c6ff64.js"},{"revision":"1ac57c116b32a82f128440f28fe90518","url":"assets/VControl.243637c8.css"},{"revision":"553b96604a1bb3ec3ef6d7b517942fe1","url":"assets/VControl.66ed690d.css"},{"revision":"7cbf6d5575240d18f109593c3ef507da","url":"assets/VControl.8f7a9833.js"},{"revision":"1d09c013e6dd0dfe630fb829404e9543","url":"assets/VControl.c5bb8a1f.js"},{"revision":"fffc3df54fcd1bfab5813accdfabbefe","url":"assets/VControl.f084cf9c.js"},{"revision":"153e4c5fe7bed71a96ca4fc8277bbd0e","url":"assets/VDropdown.3ac27351.js"},{"revision":"3e50a812f655461339a81c9afd7516da","url":"assets/VDropdown.79a9bddc.css"},{"revision":"5fe0e2a8ab0ed5d997433c87c47cefb1","url":"assets/VDropdown.d3816d35.js"},{"revision":"b4d071c6b30beb009bbfae42168ccd15","url":"assets/VDropdown.e88bb20d.js"},{"revision":"5978acc8731b5c590c217387356e4338","url":"assets/VDropdown.ecf63e77.css"},{"revision":"25dc6f2095bba2c74b452e7b18e83578","url":"assets/vendor.4fd38f72.js"},{"revision":"56f52929550a6a38873721e0f6f53888","url":"assets/vendor.73f133b9.js"},{"revision":"56f52929550a6a38873721e0f6f53888","url":"assets/vendor.e5526731.js"},{"revision":"28b4058ebf25443ee4ce22c57c5a070f","url":"assets/VField.29c5f7a2.js"},{"revision":"63830049b66cb88974bbf2bacdce2da2","url":"assets/VField.2d5f60f9.js"},{"revision":"99aeca4684f8d6a0df93a24d7c637d48","url":"assets/VField.cf44fb41.js"},{"revision":"6a0c52adec48102cfe6df74ae10b4218","url":"assets/VIconButton.01ed372b.js"},{"revision":"62a8b9f712b5758099bc4da86a46cfec","url":"assets/VIconButton.30d33551.js"},{"revision":"4370518612a913e67b83a1f69093e71e","url":"assets/VIconButton.4c00b77a.js"},{"revision":"d13c442d4a318f4be56219bf383ecc06","url":"assets/VIconButton.4c619dc7.js"},{"revision":"380217218ffd3c101415b9771853c7c6","url":"assets/VIconButton.f30fb8fa.js"},{"revision":"887a88cf4a10bf0037e3175a28c6f455","url":"assets/VIconButton.ff12a856.js"},{"revision":"e69d222d340a48ce03a640978067990d","url":"assets/VModal.794ccc5c.js"},{"revision":"e59d622308c6001fe6d4e5c066f83a0f","url":"assets/VModal.c5087f3a.js"},{"revision":"2efa864dca2c26bfcaa1a4826eb9504f","url":"assets/VModal.d8de09e0.css"},{"revision":"bdc55d7958a52e82ed94d16e8fbc4c39","url":"assets/VModal.faedfed7.js"},{"revision":"b6699993274f319fa597b864350e09a6","url":"assets/vue-tippy.esm-bundler.2ffba17f.js"},{"revision":"946a2f257c106ad89d81a78aac833aab","url":"assets/vue-tippy.esm-bundler.8cc1a8fa.js"},{"revision":"610cfb387bc2de4ac48817ca68a12b05","url":"assets/vue-tippy.esm-bundler.f3f5d806.js"},{"revision":"2f13e65d1cae7021d0313ab3ab07061d","url":"index.html"},{"revision":"4c8b74382b4f6b2cf5f8afcb87e80abc","url":"vendors/font-awesome-v5.css"},{"revision":"4bb4c5797d6ce8bd02b13e2d12c34bcd","url":"vendors/line-icons-pro.css"},{"revision":"84dcb5fdcc61a1daadf6607b40bd09ed","url":"vendors/loader.js"},{"revision":"238822f024eb9bd172d4d6494cacd69c","url":"vendors/prism-coldark-cold.css"},{"revision":"3dcac5b40fced888f5563eaf6521c1cd","url":"favicon.svg"},{"revision":"2608995d3ce047aed1b4f12314b971e6","url":"favicon.ico"},{"revision":"f77c87f977e0fcce05a6df46c885a129","url":"robots.txt"},{"revision":"b1fc7ba21cbe0c252ddf4e374dff5bcf","url":"apple-touch-icon.png"},{"revision":"598ac9f6ba4777c6a0839a61f484cc95","url":"pwa-192x192.png"},{"revision":"fdde4a327d6c825b405236efbb8da6e3","url":"pwa-512x512.png"},{"revision":"01f69260a10db7804bff725438665e08","url":"manifest.webmanifest"}]);
registerRoute(({ url }) => url.href.startsWith("https"), new NetworkFirst());
