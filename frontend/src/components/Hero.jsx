import React, { useEffect, useRef } from 'react';

/* Subtle geometric background pattern using SVG */
function HeroPattern() {
    return (
        <div
            className="absolute inset-0 pointer-events-none"
            style={{
                backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%236366f1' fill-opacity='0.07'%3E%3Ccircle cx='30' cy='30' r='1.5'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`,
                backgroundSize: '60px 60px',
            }}
        />
    );
}

export default function Hero() {
    return (
        <section className="relative min-h-screen flex items-center justify-center overflow-hidden pt-20"
            style={{ background: '#030712' }}
        >
            {/* Dot Pattern */}
            <HeroPattern />

            {/* Single, subtle top-center vignette — no multi-layer gradients */}
            <div
                className="absolute inset-0 pointer-events-none"
                style={{
                    background: 'radial-gradient(ellipse 70% 45% at 50% 0%, rgba(99,102,241,0.14) 0%, transparent 65%)',
                }}
            />

            {/* Horizontal accent line near top */}
            <div className="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-brand-500/40 to-transparent" />

            {/* Content */}
            <div className="relative z-10 max-w-7xl mx-auto px-6 text-center">
                {/* Badge */}
                <div
                    className="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-brand-500/25 text-sm text-brand-300 mb-8"
                    style={{
                        background: 'rgba(99,102,241,0.06)',
                        animation: 'fadeIn 0.6s ease-out forwards',
                    }}
                >
                    <span className="relative flex h-2 w-2">
                        <span className="animate-ping absolute inline-flex h-2 w-2 rounded-full bg-accent-400 opacity-75" />
                        <span className="relative inline-flex rounded-full h-2 w-2 bg-accent-400" />
                    </span>
                    Powered by Ethereum Smart Contracts
                </div>

                {/* Headline */}
                <h1
                    className="text-5xl md:text-7xl lg:text-8xl font-extrabold leading-tight tracking-tight mb-6"
                    style={{ animation: 'slideUp 0.8s ease-out 0.1s both' }}
                >
                    Certificates You Can
                    <br />
                    <span className="gradient-text">Trust Forever</span>
                </h1>

                {/* Subheadline */}
                <p
                    className="text-xl md:text-2xl text-slate-400 max-w-3xl mx-auto mb-10 leading-relaxed font-light"
                    style={{ animation: 'slideUp 0.8s ease-out 0.25s both' }}
                >
                    CertiLedger stores academic certificates immutably on the Ethereum blockchain.
                    Anyone, anywhere can verify authenticity in seconds — no middlemen, no fraud.
                </p>

                {/* CTA Buttons */}
                <div
                    className="flex flex-col sm:flex-row items-center justify-center gap-4 mb-16"
                    style={{ animation: 'slideUp 0.8s ease-out 0.4s both' }}
                >
                    <a href="#" className="btn-primary text-base px-8 py-4 w-full sm:w-auto justify-center">
                        <span className="relative z-10">Issue Certificate</span>
                        <svg className="w-5 h-5 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </a>
                    <a href="#verify" className="btn-secondary text-base px-8 py-4 w-full sm:w-auto justify-center">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        Verify a Certificate
                    </a>
                </div>

                {/* Certificate Card */}
                <div
                    className="relative mx-auto max-w-2xl"
                    style={{ animation: 'slideUp 0.8s ease-out 0.55s both' }}
                >
                    <div className="rounded-2xl p-6 border border-white/8 animate-float"
                        style={{ background: 'rgba(255,255,255,0.03)', backdropFilter: 'blur(24px)' }}
                    >
                        <div className="flex items-start justify-between mb-4">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-lg border border-brand-500/25 flex items-center justify-center"
                                    style={{ background: 'rgba(99,102,241,0.1)' }}>
                                    <svg className="w-5 h-5 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div className="text-left">
                                    <p className="text-xs text-slate-500 font-mono">CERT-2024-0x7F3A</p>
                                    <p className="text-sm font-semibold text-white">Bachelor of Computer Science</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-accent-500/25"
                                style={{ background: 'rgba(52,211,153,0.06)' }}>
                                <div className="w-1.5 h-1.5 rounded-full bg-accent-400 animate-pulse" />
                                <span className="text-xs font-medium text-accent-400">Verified</span>
                            </div>
                        </div>
                        <div className="grid grid-cols-3 gap-3 text-left">
                            {[
                                { label: 'Student', value: 'Rahul Sharma' },
                                { label: 'University', value: 'IIT Bombay' },
                                { label: 'Year', value: '2024' },
                            ].map(({ label, value }) => (
                                <div key={label} className="rounded-lg p-3 border border-white/5"
                                    style={{ background: 'rgba(255,255,255,0.02)' }}>
                                    <p className="text-xs text-slate-500 mb-1">{label}</p>
                                    <p className="text-sm font-medium text-white">{value}</p>
                                </div>
                            ))}
                        </div>
                        <div className="mt-4 pt-4 border-t border-white/5 flex items-center gap-2">
                            <svg className="w-3.5 h-3.5 text-brand-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                            <p className="text-xs text-slate-500 font-mono truncate">0x7F3A9E2B1C4D8F6A0E1B2C3D4E5F6A7B8C9D0E1F2...</p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Bottom fade out */}
            <div className="absolute bottom-0 left-0 right-0 h-32 pointer-events-none"
                style={{ background: 'linear-gradient(to top, #030712, transparent)' }} />

            {/* Scroll indicator */}
            <div
                className="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2"
                style={{ animation: 'fadeIn 1s ease-out 1s both' }}
            >
                <span className="text-xs text-slate-600 font-medium tracking-widest uppercase">Scroll</span>
                <div className="w-px h-8 animate-beam"
                    style={{ background: 'linear-gradient(to bottom, rgba(99,102,241,0.5), transparent)' }} />
            </div>
        </section>
    );
}
