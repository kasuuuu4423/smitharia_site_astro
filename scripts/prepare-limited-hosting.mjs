import { mkdir, rename, rm, access } from 'node:fs/promises';
import { resolve, join } from 'node:path';
import { pathToFileURL } from 'node:url';

// Hosting's static files take precedence over rewrites. Move private HTML out
// of dist entirely, including on repeated builds, before any deployment.
export async function prepareLimitedHosting(publicDir, privateDir) {
  await access(join(publicDir, 'limited/index.html'));
  await rm(privateDir, { recursive: true, force: true });
  await mkdir(privateDir, { recursive: true });
  await rename(join(publicDir, 'limited'), join(privateDir, 'limited'));
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
  await prepareLimitedHosting(
    new URL('../dist/', import.meta.url).pathname,
    new URL('../functions/private-site/', import.meta.url).pathname
  );
}
