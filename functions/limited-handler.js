import { fileURLToPath } from 'node:url';

const wpApi = 'https://smitharia.shimizuyasushi.com/wp-json/smitharia/v1/';
const privateRoot = fileURLToPath(new URL('./private-site/limited/', import.meta.url));

export function parseBasicCredentials(authorization) {
  if (typeof authorization !== 'string' || authorization.length > 1024) return null;
  const match = /^Basic ([A-Za-z0-9+/]+={0,2})$/i.exec(authorization);
  if (!match) return null;
  const decoded = Buffer.from(match[1], 'base64').toString('utf8');
  if (!/^[A-Za-z0-9_-]{3,64}:[\x21-\x7e]{16,128}$/.test(decoded)) return null;
  return authorization;
}

export function getPrivatePage(pathname) {
  let decoded;
  try {
    decoded = decodeURIComponent(pathname);
  } catch {
    return null;
  }
  if ([...decoded].some((character) => character.charCodeAt(0) <= 0x20)) return null;
  // Only generated HTML routes are served; never arbitrary function files.
  const match = /^\/limited(?:\/(work\/\d+|[^/\\.]+|aboutus))?\/?(?:index\.html)?$/.exec(decoded);
  if (!match) return null;
  const route = match[1] ?? '';
  return route ? `${route}/index.html` : 'index.html';
}

export function getPostsQuery(searchParams) {
  const validators = {
    per_page: /^(?:[1-9]|[1-9]\d|100)$/,
    page: /^[1-9]\d{0,5}$/,
    categories: /^\d+(?:,\d+)*$/,
    is_recommend: /^(true|false)$/,
    limited: /^(include|exclude|only)$/,
  };
  const result = new URLSearchParams();
  for (const [key, value] of searchParams) {
    if (!validators[key]?.test(value) || value.length > 256 || result.has(key)) return null;
    result.set(key, value);
  }
  if (!result.has('limited')) result.set('limited', 'include');
  return result;
}

export function createLimitedHandler({ getServiceKey, fetchImpl = fetch, root = privateRoot }) {
  return async (request, response) => {
    response.set({
      'Cache-Control': 'private, no-store, max-age=0',
      Vary: 'Authorization',
      'X-Robots-Tag': 'noindex, nofollow',
      'X-Content-Type-Options': 'nosniff',
    });
    if (!['GET', 'HEAD'].includes(request.method)) {
      response.set('Allow', 'GET, HEAD').status(405).send('Method not allowed');
      return;
    }
    const authorization = parseBasicCredentials(request.get('Authorization'));
    if (!authorization) {
      response.set('WWW-Authenticate', 'Basic realm="Smitharia limited", charset="UTF-8"');
      response.status(401).send('IDとパスワードを入力してください。');
      return;
    }
    try {
      const key = getServiceKey();
      if (!key) throw new Error('Missing service key');
      const headers = { 'X-Smitharia-Service-Key': key, 'X-Smitharia-Viewer': authorization };
      const url = new URL(request.originalUrl, 'https://smitharia.com');
      const apiRequest = /^\/limited\/api\/posts\/?$/.test(url.pathname);
      const page = apiRequest ? null : getPrivatePage(url.pathname);
      if (!apiRequest && !page) {
        response.status(404).send('Not found');
        return;
      }
      const query = apiRequest ? getPostsQuery(url.searchParams) : null;
      if (apiRequest && !query) {
        response.status(400).send('Invalid query');
        return;
      }
      const upstream = await fetchImpl(
        apiRequest ? `${wpApi}limited-posts?${query}` : `${wpApi}verify`,
        {
          method: apiRequest ? 'GET' : 'POST',
          headers,
          redirect: 'error',
          signal: AbortSignal.timeout(8000),
        }
      );
      if (upstream.status === 401) {
        response.set('WWW-Authenticate', 'Basic realm="Smitharia limited", charset="UTF-8"');
        response.status(401).send('IDまたはパスワードを確認してください。');
        return;
      }
      if (upstream.status === 429) {
        response.set('Retry-After', '900').status(429).send('15分後に再試行してください。');
        return;
      }
      if (!upstream.ok) {
        // An empty page from WordPress must never count as successful verification.
        if (apiRequest && upstream.status === 400) {
          const error = await upstream.json();
          if (error?.code === 'rest_post_invalid_page_number') {
            response.status(200).json([]);
            return;
          }
          response.status(400).send('Invalid query');
          return;
        }
        throw new Error('WordPress authentication unavailable');
      }
      const data = await upstream.json();
      if (apiRequest) {
        if (!Array.isArray(data)) throw new Error('Invalid posts response');
        response.status(200).json(data);
        return;
      }
      if (data?.authenticated !== true) throw new Error('Invalid verification response');
      response.sendFile(page, { root, dotfiles: 'deny', cacheControl: false }, (error) => {
        if (error && !response.headersSent)
          response.status(error.statusCode === 404 ? 404 : 500).send('Page unavailable');
      });
    } catch {
      response.status(503).send('認証サービスに接続できません。時間をおいて再試行してください。');
    }
  };
}
