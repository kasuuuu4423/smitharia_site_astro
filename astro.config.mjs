import { defineConfig } from 'astro/config';
import react from "@astrojs/react";
//import nodejs from '@astrojs/node';

import tailwind from "@astrojs/tailwind";

import node from "@astrojs/node";

// https://astro.build/config
export default defineConfig({
  //output: "hybrid",
  integrations: [react(), tailwind()],
  image: {
    domains: ["smitharia.shimizuyasushi.com"]
  },
  // adapter: node({
  //   mode: "standalone"
  // })
});