/**
 * Vite build for UiKit IIFE assets → `src/Resources/public/js/*.js`.
 * Controlled by VITE_ENTRY. Default: modal.
 * Run all: `pnpm run build`
 */
import { defineConfig } from 'vite';

type Entry =
  | 'modal'
  | 'shell'
  | 'toast'
  | 'confirm'
  | 'page-loader'
  | 'theme'
  | 'orb'
  | 'clipboard'
  | 'tabs';

const entry = process.env.VITE_ENTRY as Entry | undefined;

const configs: Record<Entry, { build: object }> = {
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
  toast: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-toast.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-toast.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  confirm: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-confirm.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-confirm.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  'page-loader': {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-page-loader.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-page-loader.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  theme: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-theme.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-theme.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  orb: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-orb.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-orb.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  clipboard: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-clipboard.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-clipboard.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  tabs: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-tabs.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-tabs.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
};

const effectiveEntry: Entry = entry && configs[entry] ? entry : 'modal';

export default defineConfig(configs[effectiveEntry]);
