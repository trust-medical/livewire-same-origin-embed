import { defineConfig } from 'vite';

export default defineConfig({
  publicDir: false,
  build: {
    lib: {
      entry: 'resources/js/bridge.ts',
      name: 'SameOriginLivewireBridge',
      formats: ['iife'],
      fileName: () => 'livewire-bridge.js',
    },
    outDir: 'dist',
    emptyOutDir: true,
    sourcemap: true,
  },
});
