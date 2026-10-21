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
  | 'theme-boot'
  | 'orb'
  | 'clipboard'
  | 'tabs'
  | 'release'
  | 'open-modal'
  | 'dialog'
  | 'popover'
  | 'segmented'
  | 'autosubmit'
  | 'error-retry';

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
  'theme-boot': {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-theme-boot.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-theme-boot.js',
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
  release: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-release.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-release.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  'open-modal': {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-open-modal.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-open-modal.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  dialog: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-dialog.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-dialog.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  popover: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-popover.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-popover.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  segmented: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-segmented.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-segmented.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  autosubmit: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-autosubmit.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-autosubmit.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  'error-retry': {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-error-retry.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-error-retry.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
};

const effectiveEntry: Entry = entry && configs[entry] ? entry : 'modal';

export default defineConfig(configs[effectiveEntry]);
