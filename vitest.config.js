import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

// Separate from vite.config.js on purpose: that file's Laravel/Netlify
// plugins expect a real `npm run build` output (public/build/manifest.json)
// to exist, which isn't the case when just running unit tests.
export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        globals: false,
        include: ['resources/js/__tests__/**/*.test.js'],
    },
});