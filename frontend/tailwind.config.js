/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        ink:    '#000000',
        paper:  '#ffffff',
        dim:    '#888888',
        muted:  '#444444',
        wire:   '#1a1a1a',   /* subtle surface */
        rule:   '#222222',   /* borders */
      },
      fontFamily: {
        sans: ['"Mona Sans"', 'system-ui', 'sans-serif'],
        mono: ['"JetBrains Mono"', '"Fira Code"', 'monospace'],
      },
      fontSize: {
        '10xl': ['10rem',  { lineHeight: '0.9', letterSpacing: '-0.03em' }],
        '9xl':  ['8rem',   { lineHeight: '0.9', letterSpacing: '-0.03em' }],
      },
      animation: {
        'reveal':        'reveal 0.9s cubic-bezier(0.16,1,0.3,1) both',
        'reveal-slow':   'reveal 1.2s cubic-bezier(0.16,1,0.3,1) both',
        'fade':          'fade 0.7s ease both',
        'scan':          'scan 3s linear infinite',
        'blink':         'blink 1.2s step-end infinite',
        'counter-in':    'counterIn 0.6s cubic-bezier(0.16,1,0.3,1) both',
        'slide-up':      'slideUp 0.8s cubic-bezier(0.16,1,0.3,1) both',
      },
      keyframes: {
        reveal: {
          from: { clipPath: 'inset(0 100% 0 0)' },
          to:   { clipPath: 'inset(0 0% 0 0)' },
        },
        fade: {
          from: { opacity: '0' },
          to:   { opacity: '1' },
        },
        scan: {
          '0%':   { transform: 'translateY(-100%)' },
          '100%': { transform: 'translateY(100vh)' },
        },
        blink: {
          '0%, 100%': { opacity: '1' },
          '50%':      { opacity: '0' },
        },
        slideUp: {
          from: { opacity: '0', transform: 'translateY(32px)' },
          to:   { opacity: '1', transform: 'translateY(0)' },
        },
      },
    },
  },
  plugins: [],
};
