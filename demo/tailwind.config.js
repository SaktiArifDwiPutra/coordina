/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './app/**/*.{vue,js,ts}',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Source Sans 3"', 'system-ui', '-apple-system', 'sans-serif'],
        heading: ['"Lexend"', 'system-ui', '-apple-system', 'sans-serif'],
      },
      colors: {
        navy: {
          DEFAULT: '#1E3A5F',
          deep: '#152943',
        },
        canvas: '#F4F7FA',
        surface: '#FFFFFF',
        subtle: '#F8FAFC',
        line: '#DCE4EE',
        ink: '#0F172A',
        mutedInk: '#475569',
      },
    },
  },
  plugins: [],
}

