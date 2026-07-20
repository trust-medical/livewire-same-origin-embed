import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'jsdom',
    include: ['tests/Js/**/*.test.ts'],
    restoreMocks: true,
  },
});
