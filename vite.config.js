import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                    'resources/css/fontawesome.css',
                    'resources/css/main.css',
                    'resources/css/admin-navigation.css',
                    'resources/css/settings.css',
                    'resources/css/app-settings.css',
                    'resources/css/planner-day.css',
                    'resources/css/planner-index.css',
                    'resources/css/settlement.css',
                    'resources/css/workers.css',
                    'resources/css/login.css',
                    'resources/css/dashboard.css',
                    'resources/css/worker-base.css',
                    'resources/css/worker-menu.css',
                    'resources/css/worker-dashboard.css',
                    'resources/css/worker-schedule.css',
                    'resources/css/account-activation.css',
                    'resources/css/password-reset.css',
                    'resources/js/app.js',
                    'resources/js/app-settings.js',
                    'resources/js/planner-day.js',
                    'resources/js/planner-index.js',
                    'resources/js/settlement.js',
                    'resources/js/workers.js',
                    'resources/js/settings.js',
                    'resources/js/dashboard.js',
                    'resources/js/worker-schedule.js',
                    'resources/js/worker-dashboard.js',
                    'resources/js/activate.js',
                    'resources/js/login.js',
                    'resources/js/password-reset.js',
                    'resources/js/password-toggle.js',
                ],
            refresh: true,
        }),
    ],
});
