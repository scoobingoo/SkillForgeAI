// SPDX-License-Identifier: Apache-2.0
// Cấu hình Vite tích hợp Laravel và Tailwind CSS 4 cho giao diện Web

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js'],
      refresh: true,
    }),
    tailwindcss(),
  ],
});
