import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                    'resources/css/main.css',
                    'resources/css/system.css',
                    'resources/css/settings.css',
                    'resources/css/planner.css',
                    'resources/css/settlement.css',
                    'resources/css/workers.css',
                    'resources/css/login.css',
                    'resources/css/dashboard.css',
                    'resources/css/worker-base.css',
                    'resources/css/worker-menu.css',
                    'resources/css/worker-dashboard.css',
                    'resources/css/worker-schedule.css',
                    'resources/js/app.js',
                    'resources/js/planner-day.js',
                    'resources/js/settlement.js',
                    'resources/js/workers.js',
                    'resources/js/settings.js',
                    'resources/js/dashboard.js',
                    'resources/js/worker-schedule.js',
                ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
