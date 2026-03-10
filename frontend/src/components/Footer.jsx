import React from 'react';

const LOGO_SVG = (
    <svg width="28" height="28" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
        <polygon points="16,2 30,9 30,23 16,30 2,23 2,9" fill="none" stroke="url(#flg)" strokeWidth="2" />
        <circle cx="16" cy="16" r="5" fill="url(#flg)" />
        <defs>
            <linearGradient id="flg" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stopColor="#818cf8" />
                <stop offset="100%" stopColor="#34d399" />
            </linearGradient>
        </defs>
    </svg>
);

const footerLinks = {
    Product: ['Issue Certificate', 'Verify Certificate', 'Revoke Certificate', 'Dashboard'],
    Technology: ['Ethereum Blockchain', 'Smart Contracts', 'SHA-256 Hashing', 'Solidity'],
    Developer: ['Documentation', 'API Reference', 'GitHub', 'Changelog'],
    Company: ['About', 'Contact', 'Privacy Policy', 'Terms of Service'],
};

export default function Footer() {
    return (
        <footer className="relative border-t border-white/5 pt-20 pb-10 overflow-hidden">
            {/* Top gradient fade */}
            <div className="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-brand-500/30 to-transparent" />

            <div className="max-w-7xl mx-auto px-6">
                <div className="grid grid-cols-2 md:grid-cols-6 gap-10 mb-16">
                    {/* Brand */}
                    <div className="col-span-2">
                        <a href="#" className="flex items-center gap-3 mb-5">
                            {LOGO_SVG}
                            <span className="text-xl font-bold tracking-tight">
                                <span className="gradient-text">Certi</span>
                                <span className="text-white">Ledger</span>
                            </span>
                        </a>
                        <p className="text-slate-500 text-sm leading-relaxed max-w-xs mb-6">
                            Blockchain-powered certificate verification. Immutable records on Ethereum —
                            issue once, verify anywhere, forever.
                        </p>
                        {/* Contract address */}
                        <div className="glass-card border border-white/5 rounded-lg px-4 py-3 inline-block">
                            <p className="text-xs text-slate-600 mb-1 font-mono">Contract</p>
                            <p className="text-xs text-brand-400 font-mono truncate max-w-xs">0xEB352b98B9CCDab750E7a99E7fb0CE740Baedfcf</p>
                        </div>
                    </div>

                    {/* Links */}
                    {Object.entries(footerLinks).map(([category, links]) => (
                        <div key={category}>
                            <h4 className="text-sm font-semibold text-white mb-4 uppercase tracking-wider">{category}</h4>
                            <ul className="space-y-3">
                                {links.map(link => (
                                    <li key={link}>
                                        <a href="#" className="text-sm text-slate-500 hover:text-white transition-colors duration-200">
                                            {link}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>

                {/* Bottom bar */}
                <div className="border-t border-white/5 pt-8 flex flex-col md:flex-row items-center justify-between gap-4">
                    <p className="text-sm text-slate-600">
                        © 2024 CertiLedger. Built with Ethereum &amp; ❤️ for academic integrity.
                    </p>
                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2 text-xs text-slate-600">
                            <span className="w-2 h-2 rounded-full bg-accent-400 animate-pulse" />
                            Ethereum Mainnet: Active
                        </div>
                        <div className="flex items-center gap-3">
                            {/* GitHub icon */}
                            <a href="#" className="text-slate-600 hover:text-white transition-colors">
                                <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    );
}
