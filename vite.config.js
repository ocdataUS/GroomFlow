import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig( {
	build: {
		outDir: path.resolve( __dirname, 'plugin/bb-groomflow/assets/build' ),
		emptyOutDir: true,
		sourcemap: true,
		lib: {
			entry: path.resolve( __dirname, 'plugin/bb-groomflow/assets/src/index.js' ),
			name: 'BBGFBoard',
			formats: [ 'iife' ],
			fileName: () => 'board.js',
		},
		rollupOptions: {
			output: {
				assetFileNames: ( assetInfo ) => {
					if ( assetInfo.name === 'style.css' ) {
						return 'board.css';
					}

					return '[name][extname]';
				},
			},
		},
	},
	css: {
		preprocessorOptions: {
			scss: {
				additionalData: '',
			},
		},
	},
	publicDir: false,
	server: {
		open: false,
	},
} );
