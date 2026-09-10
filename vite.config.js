import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// UI framework aplikasi ini adalah Bootstrap 4.6 + AdminLTE 3.2 yang dilayani
// secara offline dari public/vendor (lihat resources/views/layouts/app.blade.php).
// Vite hanya membundel CSS/JS kustom milik aplikasi.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
