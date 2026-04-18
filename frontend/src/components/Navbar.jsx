import React, { useEffect, useState } from 'react';
import { useTheme } from '../context/ThemeContext';
import { Sun, Moon } from 'lucide-react';
import { Link } from 'react-router';

export default function Navbar() {
  const [scrolled, setScrolled] = useState(false);
  const { theme, toggle } = useTheme();

  useEffect(() => {
    const fn = () => setScrolled(window.scrollY > 40);
    window.addEventListener('scroll', fn, { passive: true });
    return () => window.removeEventListener('scroll', fn);
  }, []);

  return (
    <header
      className="fixed top-0 left-0 right-0 z-50 transition-all duration-300"
      style={{
        background: scrolled ? 'var(--bg)' : 'transparent',
        backdropFilter: scrolled ? 'blur(12px)' : 'none',
        borderBottom: scrolled ? '1px solid var(--border)' : '1px solid transparent',
        opacity: scrolled ? 0.95 : 1,
      }}
    >
      <div className="max-w-7xl mx-auto px-8 h-16 flex items-center justify-between">
        {/* Wordmark */}
        <a href="/" className="flex items-center gap-2.5 group">
          {/* Minimal hex mark — pure SVG, no color */}
          <svg width="18" height="20" viewBox="0 0 18 20" fill="none">
            <path
              d="M9 1 L17 5.5 L17 14.5 L9 19 L1 14.5 L1 5.5 Z"
              stroke="var(--text)"
              strokeWidth="1.5"
              fill="none"
            />
            <circle cx="9" cy="10" r="2" fill="var(--text)" />
          </svg>
          <span
            className="font-semibold tracking-tight"
            style={{ fontSize: '1rem', letterSpacing: '-0.01em', color: 'var(--text)' }}
          >
            CertiLedger
          </span>
        </a>

        {/* Nav links */}
        <nav className="hidden md:flex items-center gap-8">
          {['How it works', 'Security', 'Stats'].map(link => (
            <a
              key={link}
              href={`#${link.toLowerCase().replace(/\s+/g, '-')}`}
              className="text-sm transition-colors duration-150"
              style={{ color: 'var(--text2)', fontWeight: 400 }}
              onMouseEnter={e => (e.target.style.color = 'var(--text)')}
              onMouseLeave={e => (e.target.style.color = 'var(--text2)')}
            >
              {link}
            </a>
          ))}
        </nav>

        {/* CTA */}
        <div className="flex items-center gap-3">
          <Link
            href="/verify"
            className="hidden sm:inline-block text-sm transition-colors duration-150"
            style={{ color: 'var(--text2)' }}
            onMouseEnter={e => (e.target.style.color = 'var(--text)')}
            onMouseLeave={e => (e.target.style.color = 'var(--text2)')}
          >
            Verify
          </Link>
          <button 
            onClick={toggle} 
            className="hidden sm:flex items-center justify-center transition-colors duration-150"
            style={{ color: 'var(--text2)', width: 32, height: 32, borderRadius: '50%', border: '1px solid var(--border2)' }}
            onMouseEnter={e => { e.currentTarget.style.color = 'var(--text)'; e.currentTarget.style.borderColor = 'var(--border)'; }}
            onMouseLeave={e => { e.currentTarget.style.color = 'var(--text2)'; e.currentTarget.style.borderColor = 'var(--border2)'; }}
            title="Toggle theme"
          >
            {theme === 'dark' ? <Sun size={15} /> : <Moon size={15} />}
          </button>
          
          <Link href="/login" className="btn btn-white text-sm py-2 px-5" style={{ fontWeight: 500 }}>
            Get started
          </Link>
        </div>
      </div>
    </header>
  );
}
