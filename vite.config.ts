/**
 * Vite build for UiKit IIFE assets → `src/Resources/public/js/*.js`.
 * Controlled by VITE_ENTRY: `modal` | `shell`. Default: modal.
 * Run both: `pnpm run build`
 */
import { defineConfig } from 'vite';

type Entry = 'modal' | 'shell';

const entry = process.env.VITE_ENTRY as Entry | undefined;

const configs = {
  modal: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-modal.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-modal.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  shell: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-shell.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-shell.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
};

const effectiveEntry: Entry = entry && configs[entry] ? entry : 'modal';

export default defineConfig(configs[effectiveEntry]);
