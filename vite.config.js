
import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
  plugins: [],
  build: {
    outDir: 'assets/dist',
    emptyOutDir: true,
    assetsDir: '', // Put files directly in dist
    manifest: true,
    rollupOptions: {
      input: {
        admin: path.resolve(__dirname, 'assets/src/js/admin.js'),
        style: path.resolve(__dirname, 'assets/src/scss/admin.scss')
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: ({name}) => {
            if (/\.(css)$/.test(name ?? '')) {
                return 'css/[name].[ext]';   
            }
            return '[name].[ext]';
        }
      }
    }
  },
  // Fix for loading images/fonts in SCSS
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'assets/src')
    }
  }
});
