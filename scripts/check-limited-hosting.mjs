import { access } from 'node:fs/promises';

await access(new URL('../functions/private-site/limited/index.html', import.meta.url));
let publicLimitedExists = false;
try {
  await access(new URL('../dist/limited', import.meta.url));
  publicLimitedExists = true;
} catch (error) {
  if (error.code !== 'ENOENT') throw error;
}
if (publicLimitedExists)
  throw new Error('限定HTMLがdistに残っています。npm run buildを実行してください。');
