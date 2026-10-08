import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
  createLimitedHandler,
  getPostsQuery,
  getPrivatePage,
  parseBasicCredentials,
} from '../limited-handler.js';

const auth = `Basic ${Buffer.from('client-one:long-random-password').toString('base64')}`;
function request(url = '/limited/', authorization = auth) {
  return { method: 'GET', originalUrl: url, get: () => authorization };
}
function response() {
  return {
    headers: {},
    statusCode: 200,
    body: null,
    file: null,
    set(key, value) {
      Object.assign(this.headers, typeof key === 'object' ? key : { [key]: value });
      return this;
    },
    status(code) {
      this.statusCode = code;
      return this;
    },
    send(body) {
      this.body = body;
      return this;
    },
    json(body) {
      this.body = body;
      return this;
    },
    sendFile(file, options) {
      this.file = { file, options };
    },
  };
}

test('missing/malformed credentials never fetch or serve HTML', async () => {
  for (const authorization of [
    undefined,
    '',
    'Bearer anything',
    'Basic !!!',
    `Basic ${Buffer.from('client:short').toString('base64')}`,
  ]) {
    const handler = createLimitedHandler({
      getServiceKey: () => 'secret',
      fetchImpl: () => {
        throw new Error('Must not fetch');
      },
    });
    const result = response();
    await handler(request('/limited/', authorization === undefined ? null : authorization), result);
    assert.equal(result.statusCode, 401);
    assert.match(result.headers['WWW-Authenticate'], /^Basic /);
    assert.equal(result.file, null);
    assert.equal(result.headers['Cache-Control'], 'private, no-store, max-age=0');
  }
});

test('valid credentials are checked at WP on every page request', async () => {
  const calls = [];
  const handler = createLimitedHandler({
    getServiceKey: () => 'service-secret',
    fetchImpl: async (url, options) => {
      calls.push({ url, options });
      return Response.json({ authenticated: true });
    },
  });
  for (let count = 0; count < 2; count += 1) {
    const result = response();
    await handler(request('/limited/work/42/'), result);
    assert.equal(result.file.file, 'work/42/index.html');
    assert.equal(result.headers['X-Robots-Tag'], 'noindex, nofollow');
  }
  assert.equal(calls.length, 2);
  assert.equal(calls[0].options.headers['X-Smitharia-Viewer'], auth);
  assert.equal(calls[0].options.headers['X-Smitharia-Service-Key'], 'service-secret');
  assert.equal(calls[0].options.redirect, 'error');
});

test('revocation, throttling, WP outage and unexpected 200 responses fail closed', async () => {
  for (const [upstream, expected] of [
    [() => new Response('', { status: 401 }), 401],
    [() => new Response('', { status: 403 }), 503],
    [() => new Response('', { status: 429 }), 429],
    [() => new Response('', { status: 500 }), 503],
    [() => Response.json({}), 503],
    [() => Response.json({ authenticated: false }), 503],
    [() => new Response('<html>WP login</html>'), 503],
    [
      () => {
        throw new Error('Timeout');
      },
      503,
    ],
  ]) {
    const result = response();
    await createLimitedHandler({ getServiceKey: () => 'key', fetchImpl: upstream })(
      request(),
      result
    );
    assert.equal(result.statusCode, expected);
    assert.equal(result.file, null);
  }
});

test('missing server secret never serves a page', async () => {
  const result = response();
  await createLimitedHandler({ getServiceKey: () => '' })(request(), result);
  assert.equal(result.statusCode, 503);
  assert.equal(result.file, null);
});

test('only private HTML routes are allowed; traversal and arbitrary files fail', () => {
  assert.equal(getPrivatePage('/limited'), 'index.html');
  assert.equal(getPrivatePage('/limited/index.html'), 'index.html');
  assert.equal(getPrivatePage('/limited/aboutus/'), 'aboutus/index.html');
  assert.equal(getPrivatePage('/limited/work/123/index.html'), 'work/123/index.html');
  for (const path of [
    '/work/123',
    '/limited/../index.js',
    '/limited/%2e%2e/index.js',
    '/limited/%2f..%2findex.js',
    '/limited/foo\\bar',
    '/limited/work/123.json',
    '/limited/%00',
    '/limited/%FF',
    '/limited/%',
    '/limited/api/posts',
  ]) {
    assert.equal(getPrivatePage(path), null, path);
  }
});

test('query allows pagination/filtering but no arbitrary endpoint or parameters', () => {
  assert.equal(
    getPostsQuery(new URLSearchParams('per_page=20&page=2&limited=include')).toString(),
    'per_page=20&page=2&limited=include'
  );
  for (const query of [
    'url=https://evil.example',
    'context=edit',
    'per_page=101',
    'page=0',
    'page=1&page=2',
    'categories=1;DROP',
    'limited=private',
  ]) {
    assert.equal(getPostsQuery(new URLSearchParams(query)), null, query);
  }
});

test('authenticated API returns posts and reaches only the dedicated WP endpoint', async () => {
  const result = response();
  let fetched;
  await createLimitedHandler({
    getServiceKey: () => 'key',
    fetchImpl: async (url) => {
      fetched = url;
      return Response.json([{ id: 42, acf: { limited: true } }]);
    },
  })(request('/limited/api/posts?per_page=20&limited=include'), result);
  assert.match(fetched, /\/smitharia\/v1\/limited-posts\?per_page=20&limited=include$/);
  assert.deepEqual(result.body, [{ id: 42, acf: { limited: true } }]);
  assert.equal(result.file, null);
});

test('normal end of pagination returns empty array; other invalid queries remain errors', async () => {
  for (const [code, expected] of [
    ['rest_post_invalid_page_number', 200],
    ['rest_invalid_param', 400],
  ]) {
    const result = response();
    await createLimitedHandler({
      getServiceKey: () => 'key',
      fetchImpl: async () => Response.json({ code }, { status: 400 }),
    })(request('/limited/api/posts?page=3'), result);
    assert.equal(result.statusCode, expected);
    if (expected === 200) assert.deepEqual(result.body, []);
  }
});

test('POST and invalid queries cannot reach WP', async () => {
  const handler = createLimitedHandler({
    getServiceKey: () => 'key',
    fetchImpl: () => {
      throw new Error('Must not fetch');
    },
  });
  const post = response();
  await handler({ ...request(), method: 'POST' }, post);
  assert.equal(post.statusCode, 405);
  const invalid = response();
  await handler(request('/limited/api/posts?url=elsewhere'), invalid);
  assert.equal(invalid.statusCode, 400);
});

test('password accepts 8 to 128 printable ASCII characters and rejects invalid boundaries', () => {
  for (const password of ['Ab3!xyZ9', 'x'.repeat(128)]) {
    assert.ok(
      parseBasicCredentials(`Basic ${Buffer.from(`client-one:${password}`).toString('base64')}`)
    );
  }
  for (const password of [
    'x'.repeat(7),
    'x'.repeat(129),
    'Ab3 xyZ9',
    '日本語のパスワード',
    'Ab3!xyZ\n',
  ]) {
    assert.equal(
      parseBasicCredentials(`Basic ${Buffer.from(`client-one:${password}`).toString('base64')}`),
      null
    );
  }
});

test('password can include colon; usernames cannot contain colon or Unicode', () => {
  assert.ok(
    parseBasicCredentials(
      `Basic ${Buffer.from('client-one:password:with:colons').toString('base64')}`
    )
  );
  assert.equal(
    parseBasicCredentials(`Basic ${Buffer.from('会社:long-random-password').toString('base64')}`),
    null
  );
});
