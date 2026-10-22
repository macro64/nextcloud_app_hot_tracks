import { fileURLToPath } from 'node:url'
import path from 'node:path'
import vue from '@vitejs/plugin-vue2'
import { defineConfig } from 'vite'

const root = fileURLToPath(new URL('.', import.meta.url))

export default defineConfig({
	plugins: [vue()],
	build: {
		outDir: root,
		emptyOutDir: false,
		cssCodeSplit: false,
		rollupOptions: {
			input: path.join(root, 'src/main.js'),
			output: {
				format: 'iife',
				entryFileNames: 'js/strava-main.js',
				inlineDynamicImports: true,
				assetFileNames: (asset) => {
					const name = asset.names?.[0] ?? asset.name ?? ''
					if (name.endsWith('.css')) return 'css/strava-main.css'
					return 'js/assets/[name][extname]'
				},
			},
		},
	},
})
