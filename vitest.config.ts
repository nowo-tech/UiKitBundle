import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'happy-dom',
    include: ['src/Resources/assets/src/**/*.test.ts', 'src/Resources/assets/stimulus-peers/**/*.test.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'text-summary', 'html'],
      reportsDirectory: './coverage-ts',
      // Gate first-party IIFE sources only; vendored Thinking Orbs engine lives under orb/.
      // Stimulus peers are gated one by one as they reach full coverage.
      include: [
        'src/Resources/assets/src/*.ts',
        'src/Resources/assets/stimulus-peers/auto_refresh_controller.ts',
        'src/Resources/assets/stimulus-peers/collapse_panel_controller.ts',
        'src/Resources/assets/stimulus-peers/datatable_controller.ts',
        'src/Resources/assets/stimulus-peers/temporary_reveal_controller.ts',
      ],
      exclude: ['**/*.test.ts', '**/node_modules/**'],
      thresholds: {
        lines: 90,
        functions: 90,
        branches: 85,
        statements: 90,
      },
    },
  },
});
