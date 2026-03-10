import React, { useEffect, useRef, useState } from 'react';

const LOGO_SVG = (
    <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
        <polygon points="16,2 30,9 30,23 16,30 2,23 2,9" fill="none" stroke="url(#lg1)" strokeWidth="2" />
        <circle cx="16" cy="16" r="5" fill="url(#lg1)" />
        <line x1="16" y1="7" x2="16" y2="11" stroke="rgba(99,102,241,0.6)" strokeWidth="1.5" />
        <line x1="16" y1="21" x2="16" y2="25" stroke="rgba(99,102,241,0.6)" strokeWidth="1.5" />
        <line x1="7" y1="11.5" x2="11" y2="13.75" stroke="rgba(99,102,241,0.6)" strokeWidth="1.5" />
        <line x1="25" y1="11.5" x2="21" y2="13.75" stroke="rgba(99,102,241,0.6)" strokeWidth="1.5" />
        <line x1="7" y1="20.5" x2="11" y2="18.25" stroke="rgba(99,102,241,0.6)" strokeWidth="1.5" />
        <line x1="25" y1="20.5" x2="21" y2="18.25" stroke="rgba(99,102,241,0.6)" strokeWidth="1.5" />
        <defs>
            <linearGradient id="lg1" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stopColor="#818cf8" />
                <stop offset="100%" stopColor="#34d399" />
            </linearGradient>
        </defs>
    </svg>
);

export default function Navbar() {
    const [scrolled, setScrolled] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => {
        const handleScroll = () => setScrolled(window.scrollY > 20);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    const navLinks = [
        { label: 'How It Works', href: '#how-it-works' },
        { label: 'Features', href: '#features' },
        { label: 'Security', href: '#security' },
        { label: 'About', href: '#about' },
    ];

    return (
        <header
            className={`fixed top-0 left-0 right-0 z-50 transition-all duration-500 ${scrolled
                    ? 'py-3 glass-card border-b border-white/5'
                    : 'py-5 border-b border-transparent'
                }`}
        >
            <div className="max-w-7xl mx-auto px-6 flex items-center justify-between">
                {/* Logo */}
                <a href="#" className="flex items-center gap-3 group">
                    <div className="transition-transform duration-300 group-hover:scale-110">
                        {LOGO_SVG}
                    </div>
                    <span className="text-xl font-bold tracking-tight">
                        <span className="gradient-text">Certi</span>
                        <span className="text-white">Ledger</span>
                    </span>
                </a>

                {/* Desktop Nav */}
                <nav className="hidden md:flex items-center gap-8">
                    {navLinks.map(link => (
                        <a
                            key={link.label}
                            href={link.href}
                            className="text-sm font-medium text-slate-400 hover:text-white transition-colors duration-200 relative group"
                        >
                            {link.label}
                            <span className="absolute -bottom-0.5 left-0 w-0 h-px bg-brand-400 transition-all duration-300 group-hover:w-full" />
                        </a>
                    ))}
                </nav>

                {/* CTA Buttons */}
                <div className="hidden md:flex items-center gap-3">
                    <a href="#verify" className="btn-secondary text-sm py-2.5 px-5">
                        Verify Certificate
                    </a>
                    <a href="#" className="btn-primary text-sm py-2.5 px-5">
                        <span className="relative z-10">Get Started</span>
                        <svg className="w-4 h-4 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                </div>

                {/* Mobile Menu Toggle */}
                <button
                    className="md:hidden text-slate-400 hover:text-white"
                    onClick={() => setMobileOpen(!mobileOpen)}
                    aria-label="Toggle menu"
                >
                    {mobileOpen ? (
                        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    ) : (
                        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    )}
                </button>
            </div>

            {/* Mobile Menu */}
            <div
                className={`md:hidden transition-all duration-300 overflow-hidden ${mobileOpen ? 'max-h-96 opacity-100' : 'max-h-0 opacity-0'
                    }`}
            >
                <nav className="px-6 pt-4 pb-6 flex flex-col gap-4 glass-card border-t border-white/5 mt-3">
                    {navLinks.map(link => (
                        <a
                            key={link.label}
                            href={link.href}
                            onClick={() => setMobileOpen(false)}
                            className="text-slate-300 hover:text-white transition-colors font-medium"
                        >
                            {link.label}
                        </a>
                    ))}
                    <div className="flex flex-col gap-3 pt-2">
                        <a href="#verify" className="btn-secondary text-sm text-center py-2.5">Verify Certificate</a>
                        <a href="#" className="btn-primary text-sm text-center py-2.5 justify-center">Get Started</a>
                    </div>
                </nav>
            </div>
        </header>
    );
}
