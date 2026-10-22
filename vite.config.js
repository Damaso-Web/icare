import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import fs from 'fs';

export default defineConfig({
    // Netlify serves the contents of public/build at the site root, so its
    // assets live at /assets/..., not /build/assets/... Without this, pages that
    // load their own CSS later (e.g. Student Login) asked for /build/... and got
    // a blank screen. Laravel serving the build locally keeps the default /build/.
    base: process.env.NETLIFY ? '/' : undefined,
    // Ship source maps so the browser (and Lighthouse) can map the minified
    // bundle back to source — clears the "missing source maps" best-practice flag.
    build: { sourcemap: true },
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
    <meta name="description" content="iCARE is the Integrated Case Management System of the Benguet State University Office of Student Services — referrals, appointments and student case records in one connected place.">
    <meta name="theme-color" content="#0e2d1e">
    <meta property="og:type" content="website">
    <meta property="og:title" content="iCARE · BSU Office of Student Services">
    <meta property="og:description" content="Referrals, appointments and student case records of the BSU Office of Student Services, in one connected place.">
    <meta property="og:image" content="/icare-logo.png">
    <link rel="icon" type="image/png" href="/icare-logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=Instrument+Serif:ital@0;1&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/${cssFile}">
</head>
<body>
    <div id="app"></div>
    <script type="module" src="/${jsFile}"></script>
</body>
</html>`;

                fs.writeFileSync('./public/build/index.html', html);
                fs.writeFileSync('./public/build/_redirects', '/templates/*  /templates/:splat  200\n/*  /index.html  200');

                // Copy templates folder into the build output so Netlify can serve them
                const templatesSrc  = './public/templates';
                const templatesDest = './public/build/templates';
                if (fs.existsSync(templatesSrc)) {
                    fs.mkdirSync(templatesDest, { recursive: true });
                    fs.readdirSync(templatesSrc).forEach(file => {
                        fs.copyFileSync(`${templatesSrc}/${file}`, `${templatesDest}/${file}`);
                    });
                    console.log('✓ Templates copied to build output');
                }

                // Copy the logos and crawler/agent files into the build output so
                // Netlify serves the real files instead of the SPA fallback HTML.
                for (const logo of ['icare-logo.png', 'bsu-logo.jpg', 'oss-logo.jpg', 'robots.txt', 'llms.txt', 'ai-catalog.json']) {
                    if (fs.existsSync(`./public/${logo}`)) {
                        fs.copyFileSync(`./public/${logo}`, `./public/build/${logo}`);
                    }
                }
                console.log('✓ Logos copied to build output');

                console.log('✓ index.html and _redirects generated successfully');
            }
        }
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});