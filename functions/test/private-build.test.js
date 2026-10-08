import assert from 'node:assert/strict';
import { mkdtemp, mkdir, readFile, writeFile, rm, access } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';

import { prepareLimitedHosting } from '../../scripts/prepare-limited-hosting.mjs';

test('private HTML is moved outside Hosting, including nested work pages', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'smitharia-private-build-'));
  try {
    const publicDir = join(directory, 'dist');
    const privateDir = join(directory, 'functions/private-site');
    await mkdir(join(publicDir, 'limited/work/42'), { recursive: true });
    await writeFile(join(publicDir, 'limited/index.html'), 'private index');
    await writeFile(join(publicDir, 'limited/work/42/index.html'), 'private work');
    await writeFile(join(publicDir, 'index.html'), 'public index');
    await prepareLimitedHosting(publicDir, privateDir);
    await assert.rejects(access(join(publicDir, 'limited')));
    assert.equal(
      await readFile(join(privateDir, 'limited/work/42/index.html'), 'utf8'),
      'private work'
    );
    assert.equal(await readFile(join(publicDir, 'index.html'), 'utf8'), 'public index');
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('failed builds cannot erase the previous private bundle', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'smitharia-private-build-'));
  try {
    const privateDir = join(directory, 'private-site');
    await mkdir(privateDir);
    await writeFile(join(privateDir, 'previous.html'), 'previous bundle');
    await assert.rejects(prepareLimitedHosting(join(directory, 'missing-dist'), privateDir));
    assert.equal(await readFile(join(privateDir, 'previous.html'), 'utf8'), 'previous bundle');
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});
