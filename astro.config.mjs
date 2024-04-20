import { defineConfig } from 'astro/config';
import react from "@astrojs/react";
//import nodejs from '@astrojs/node';

import tailwind from "@astrojs/tailwind";
import node from "@astrojs/node";

import sitemap from "@astrojs/sitemap";
import partytown from "@astrojs/partytown";

// https://astro.build/config
export default defineConfig({
  //output: "hybrid",
  integrations: [
    react(),
    tailwind(),
    sitemap(),
    partytown({
      // Adds dataLayer.push as a forwarding-event.
      config: {
        forward: ["dataLayer.push"],
      },
    }),
  ],
  image: {
    domains: ["smitharia.shimizuyasushi.com"]
  }
  // adapter: node({
  //   mode: "standalone"
  // })
});