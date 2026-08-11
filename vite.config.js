import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import fs from 'fs';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        {
            name: 'generate-index-html',
            closeBundle() {
                const manifest = JSON.parse(fs.readFileSync('./public/build/manifest.json', 'utf-8'));
                const entry    = manifest['resources/js/app.js'];
                const jsFile   = entry.file;
                const cssFile  = entry.css?.[0] || '';

                const html = `<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>iCARE · BSU Office of Student Services</title>
            <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=Instrument+Serif:ital@0;1&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="./${cssFile}">
        </head>
        <body>
            <div id="app"></div>
            <script type="module" src="./${jsFile}"></script>
        </body>
        </html>`;

                fs.writeFileSync('./public/build/index.html', html);
                console.log('✓ index.html generated successfully');
            }
        }
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});