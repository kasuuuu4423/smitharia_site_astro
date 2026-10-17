import { defineConfig } from 'astro/config';
import react from '@astrojs/react';
//import nodejs from '@astrojs/node';

import tailwind from '@astrojs/tailwind';
import node from '@astrojs/node';

import sitemap from '@astrojs/sitemap';
import partytown from '@astrojs/partytown';

const partytownIntegration = partytown({
  config: { forward: ['dataLayer.push'] },
});
const setupPartytown = partytownIntegration.hooks['astro:config:setup'];
// Partytownの短縮変数をページのグローバルスコープに宣言しない。
partytownIntegration.hooks['astro:config:setup'] = (options) =>
  setupPartytown({
    ...options,
    injectScript(stage, content) {
      options.injectScript(
        stage,
        stage === 'head-inline' ? `(() => {\n${content}\n})();` : content
      );
    },
  });

// https://astro.build/config
export default defineConfig({
  site: 'https://smitharia.com',
  //output: "hybrid",
  integrations: [
    react(),
    tailwind(),
    sitemap({ filter: (page) => !new URL(page).pathname.startsWith('/limited/') }),
    partytownIntegration,
  ],
  image: {
    domains: ['smitharia.shimizuyasushi.com'],
  },
  // adapter: node({
  //   mode: "standalone"
  // })
});
