import React, { useEffect, useRef } from 'react';

function useScrollAnimation() {
    const ref = useRef(null);
    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    el.querySelectorAll(
                        '.animate-in, .animate-in-left, .animate-in-right, .animate-in-scale'
                    ).forEach(c => c.classList.add('visible'));
                }
            },
            { threshold: 0.08 }
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, []);
    return ref;
}

export default function Security() {
    const ref = useScrollAnimation();

    return (
        <section id="security" ref={ref} className="relative py-32 overflow-hidden">
            {/* Grid pattern */}
            <div className="absolute inset-0 bg-pattern-grid pointer-events-none" />
            <div className="absolute top-0 left-0 right-0 h-px" style={{ background: 'linear-gradient(90deg, transparent, rgba(99,102,241,0.2), transparent)' }} />

            <div className="max-w-7xl mx-auto px-6">
                <div className="grid lg:grid-cols-2 gap-16 items-center">

                    {/* Left: Security card — slides in from left */}
                    <div className="animate-in-left relative order-2 lg:order-1">
                        <div className="relative">
                            <div className="rounded-3xl p-8 border border-white/6 animate-float"
                                style={{ background: 'rgba(255,255,255,0.025)', backdropFilter: 'blur(24px)' }}>

                                {/* Icon — solid color, no gradient */}
                                <div className="w-14 h-14 rounded-2xl bg-brand-600 flex items-center justify-center mb-6">
                                    <svg className="w-7 h-7" style={{ color: 'var(--bg)' }} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>

                                <h3 className="text-lg font-bold mb-1" style={{ color: 'var(--text)' }}>Smart Contract Security</h3>
                                <p className="text-sm mb-6 font-mono" style={{ color: 'var(--text2)' }}>CertificateRegistry.sol</p>

                                <div className="space-y-3">
                                    {[
                                        'Access Control — Only authorized issuers',
                                        'Input Validation — All fields verified on-chain',
                                        'Immutable Storage — Cannot be altered after issuance',
                                        'Revocation System — Admin-controlled revocation',
                                        'Private Key Protection — Never exposed to frontend',
                                        'Rate Limiting — Abuse prevention built in',
                                    ].map((check) => (
                                        <div key={check} className="flex items-center gap-3">
                                            <div className="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0"
                                                style={{ background: 'var(--border2)' }}>
                                                <svg className="w-3 h-3" style={{ color: 'var(--text)' }} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                                                </svg>
                                            </div>
                                            <span className="text-sm" style={{ color: 'var(--text2)' }}>{check}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Floating badge */}
                            <div className="absolute -top-4 -right-4 border rounded-xl px-4 py-3 animate-float-delayed"
                                style={{ borderColor: 'var(--border)', background: 'var(--surface)', backdropFilter: 'blur(12px)' }}>
                                <div className="flex items-center gap-2">
                                    <div className="w-2 h-2 rounded-full animate-pulse" style={{ background: 'var(--text)' }} />
                                    <span className="text-xs font-semibold" style={{ color: 'var(--text2)' }}>Ethereum Secured</span>
                                </div>
                            </div>

                            {/* Rotating rings — subtle indicators, no gradient */}
                            <div className="absolute -inset-8 rounded-full border border-brand-500/8 animate-spin-slow pointer-events-none" />
                            <div className="absolute -inset-16 rounded-full border border-brand-500/4 animate-spin-slow-reverse pointer-events-none" />
                        </div>
                    </div>

                    {/* Right: Content — slides in from right */}
                    <div className="order-1 lg:order-2">
                        <div className="animate-in-right">
                            <span className="inline-block text-xs font-semibold tracking-widest uppercase border px-4 py-2 rounded-full mb-6"
                                style={{ color: 'var(--text)', borderColor: 'var(--border)', background: 'var(--surface)' }}>
                                Security First
                            </span>
                            <h2 className="text-4xl md:text-5xl font-extrabold mb-6" style={{ color: 'var(--text)' }}>
                                Fraud-Proof by <span style={{ color: 'var(--text2)' }}>Design</span>
                            </h2>
                            <p className="text-lg mb-8 leading-relaxed" style={{ color: 'var(--text2)' }}>
                                Traditional certificates can be faked. CertiLedger makes that impossible. Every certificate's
                                cryptographic hash is stored permanently on Ethereum — a change of even one character
                                produces a completely different hash and fails verification instantly.
                            </p>
                        </div>

                        <div className="space-y-4 animate-in-right animate-in-delay-2">
                            {[
                                {
                                    title: 'Decentralized Validation',
                                    desc: 'No single point of failure. Ethereum validates every transaction across thousands of nodes.',
                                    icon: '🌐',
                                },
                                {
                                    title: 'SHA-256 Hash Binding',
                                    desc: 'The hash uniquely fingerprints every certificate. Tampering is mathematically impossible to conceal.',
                                    icon: '#️⃣',
                                },
                                {
                                    title: 'Transparent & Auditable',
                                    desc: 'Every issuance and revocation is a public blockchain event, fully auditable by anyone.',
                                    icon: '🔍',
                                },
                            ].map(item => (
                                <div
                                    key={item.title}
                                    className="flex gap-4 rounded-xl p-4 border transition-colors duration-200"
                                    style={{ background: 'var(--surface)', borderColor: 'var(--border)' }}
                                >
                                    <div className="text-2xl flex-shrink-0 mt-0.5">{item.icon}</div>
                                    <div>
                                        <h4 className="font-semibold mb-1" style={{ color: 'var(--text)' }}>{item.title}</h4>
                                        <p className="text-sm leading-relaxed" style={{ color: 'var(--text2)' }}>{item.desc}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
