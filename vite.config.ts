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
  | 'tree'
  | 'side-panel'
  | 'avatar'
  | 'header-crumbs'
  | 'autosubmit'
  | 'error-retry'
  | 'overlay'
  | 'scroll-region'
  | 'sortable'
  | 'emoji-picker'
  | 'show-more'
  | 'edge-tabs'
  | 'image-crop'
  | 'demo-peers';

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
  overlay: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-overlay.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-overlay.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  'scroll-region': {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-scroll-region.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-scroll-region.js',
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
  tree: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-tree.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-tree.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  'side-panel': {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-side-panel.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-side-panel.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  avatar: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-avatar.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-avatar.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  'header-crumbs': {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-header-crumbs.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-header-crumbs.js',
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
  sortable: {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-sortable.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-sortable.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  'emoji-picker': {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-emoji-picker.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-emoji-picker.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  'show-more': {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-show-more.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-show-more.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  // 2.41 (MUX-01): opt-in page-edge tabs launcher.
  'edge-tabs': {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-edge-tabs.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-edge-tabs.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  // 2.43: opt-in crop step for image file inputs.
  'image-crop': {
    build: {
      outDir: 'src/Resources/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'src/Resources/assets/src/nowo-ui-image-crop.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/nowo-ui-image-crop.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
  // Demo only (UiKit 2.23 demos): Stimulus + the kit peers those pages use, committed to demo/symfony8/public/js.
  'demo-peers': {
    build: {
      outDir: 'demo/symfony8/public',
      emptyOutDir: false,
      rollupOptions: {
        input: 'demo/symfony8/assets/demo-peers.ts',
        output: {
          format: 'iife' as const,
          entryFileNames: 'js/demo-peers.js',
        },
      },
      minify: true,
      sourcemap: false,
    },
  },
};

const effectiveEntry: Entry = entry && configs[entry] ? entry : 'modal';

export default defineConfig(configs[effectiveEntry]);
