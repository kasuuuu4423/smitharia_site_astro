/** @type {import('tailwindcss').Config} */
const defaultTheme = require("tailwindcss/defaultTheme");

export default {
	content: ['./src/**/*.{astro,html,js,jsx,md,mdx,svelte,ts,tsx,vue}'],
	theme: {
		extend: {
			fontFamily: {
				serif: ["big-caslon-fb", ...defaultTheme.fontFamily.serif],
				sans: ["游ゴシック体", "YuGothic", "游ゴシック", "Yu Gothic", ...defaultTheme.fontFamily.sans],
			},
		},
	},
	plugins: [],
}
