import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import statamic from '@statamic/cms/vite-plugin';
import fs from 'fs';
import { homedir } from 'os';

const certPath = `${homedir()}/Library/Application Support/Herd/config/valet/Certificates`;

export default defineConfig({
    server: {
        host: 'statamicaddons.test',
        port: 5181,
        strictPort: true,
        https: {
            key: fs.readFileSync(`${certPath}/statamicaddons.test.key`),
            cert: fs.readFileSync(`${certPath}/statamicaddons.test.crt`),
        },
    },
    plugins: [
        statamic(),
        laravel({
            input: ['resources/js/addon.js'],
            publicDirectory: 'resources/dist',
            buildDirectory: 'build',
        }),
    ],
});
