/** Same engine and major version as the former Play CDN (cdn.tailwindcss.com serves Tailwind 3.4). */
module.exports = {
  content: [
    './templates/**/*.twig',
    // The bundle's templates and the PHP that maps components to framework classes.
    '../../src/Resources/views/**/*.twig',
    '../../src/**/*.php',
    '../../src/Resources/assets/**/*.{ts,js}',
  ],
  theme: { extend: {} },
  plugins: [],
};
