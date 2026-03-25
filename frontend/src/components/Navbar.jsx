import React, { useEffect, useState } from 'react';

export default function Navbar() {
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const fn = () => setScrolled(window.scrollY > 40);
    window.addEventListener('scroll', fn, { passive: true });
    return () => window.removeEventListener('scroll', fn);
  }, []);

  return (
    <header
      className="fixed top-0 left-0 right-0 z-50 transition-all duration-300"
      style={{
        background: scrolled ? 'rgba(0,0,0,0.92)' : 'transparent',
        backdropFilter: scrolled ? 'blur(12px)' : 'none',
        borderBottom: scrolled ? '1px solid #1a1a1a' : '1px solid transparent',
      }}
    >
      <div className="max-w-7xl mx-auto px-8 h-16 flex items-center justify-between">
        {/* Wordmark */}
        <a href="#" className="flex items-center gap-2.5 group">
          {/* Minimal hex mark — pure SVG, no color */}
          <svg width="18" height="20" viewBox="0 0 18 20" fill="none">
            <path
              d="M9 1 L17 5.5 L17 14.5 L9 19 L1 14.5 L1 5.5 Z"
              stroke="white"
              strokeWidth="1.5"
              fill="none"
            />
            <circle cx="9" cy="10" r="2" fill="white" />
          </svg>
          <span
            className="text-white font-semibold tracking-tight"
            style={{ fontSize: '1rem', letterSpacing: '-0.01em' }}
          >
            CertiLedger
          </span>
        </a>

        {/* Nav links */}
        <nav className="hidden md:flex items-center gap-8">
          {['How it works', 'Security', 'Protocol'].map(link => (
            <a
              key={link}
              href={`#${link.toLowerCase().replace(/\s+/g, '-')}`}
              className="text-sm transition-colors duration-150"
              style={{ color: '#666', fontWeight: 400 }}
              onMouseEnter={e => (e.target.style.color = '#fff')}
              onMouseLeave={e => (e.target.style.color = '#666')}
            >
              {link}
            </a>
          ))}
        </nav>

        {/* CTA */}
        <div className="flex items-center gap-3">
          <a
            href="#verify"
            className="hidden sm:inline-block text-sm transition-colors duration-150"
            style={{ color: '#666' }}
            onMouseEnter={e => (e.target.style.color = '#fff')}
            onMouseLeave={e => (e.target.style.color = '#666')}
          >
            Verify
          </a>
          <a href="#" className="btn btn-white text-sm py-2 px-5" style={{ fontWeight: 500 }}>
            Get started
          </a>
        </div>
      </div>
    </header>
  );
}
