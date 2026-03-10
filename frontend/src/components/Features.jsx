import React, { useEffect, useRef } from 'react';

const features = [
    {
        icon: (
            <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        ),
        title: 'Immutable on Ethereum',
        description: 'Certificates are written to Ethereum smart contracts — permanent, tamper-proof, and decentralized by design.',
        tag: 'Blockchain',
        tagColor: 'text-brand-300 border-brand-500/25',
        tagBg: 'rgba(99,102,241,0.07)',
        hoverBorder: 'hover:border-brand-500/25',
        iconColor: 'text-brand-400',
    },
    {
        icon: (
            <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        ),
        title: 'Instant Public Verification',
        description: 'Anyone can verify any certificate without an account. Just enter a certificate ID and get results in seconds.',
        tag: 'Open Access',
        tagColor: 'text-accent-300 border-accent-500/25',
        tagBg: 'rgba(52,211,153,0.07)',
        hoverBorder: 'hover:border-accent-500/25',
        iconColor: 'text-accent-400',
    },
    {
        icon: (
            <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
        ),
        title: 'Cryptographic Proof',
        description: 'Every certificate carries a SHA-256 hash stored on-chain. Even a single character change is detected and rejected.',
        tag: 'Cryptography',
        tagColor: 'text-violet-300 border-violet-500/25',
        tagBg: 'rgba(139,92,246,0.07)',
        hoverBorder: 'hover:border-violet-500/25',
        iconColor: 'text-violet-400',
    },
    {
        icon: (
            <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        ),
        title: 'Role-Based Access Control',
        description: 'Only admin-authorized university wallets can issue certificates. Revocation is handled securely by the contract admin.',
        tag: 'Access Control',
        tagColor: 'text-orange-300 border-orange-500/25',
        tagBg: 'rgba(249,115,22,0.07)',
        hoverBorder: 'hover:border-orange-500/25',
        iconColor: 'text-orange-400',
    },
    {
        icon: (
            <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064" />
            </svg>
        ),
        title: 'Globally Accessible',
        description: 'No geographic restrictions. The Ethereum blockchain is accessible worldwide — from New York recruiters to Tokyo universities.',
        tag: 'Decentralized',
        tagColor: 'text-cyan-300 border-cyan-500/25',
        tagBg: 'rgba(6,182,212,0.07)',
        hoverBorder: 'hover:border-cyan-500/25',
        iconColor: 'text-cyan-400',
    },
    {
        icon: (
            <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
        ),
        title: 'Certificate Revocation',
        description: 'Admin can revoke compromised or erroneous certificates permanently. Revocation is publicly and transparently recorded on-chain.',
        tag: 'Admin Control',
        tagColor: 'text-rose-300 border-rose-500/25',
        tagBg: 'rgba(244,63,94,0.07)',
        hoverBorder: 'hover:border-rose-500/25',
        iconColor: 'text-rose-400',
    },
];

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
                    ).forEach(child => child.classList.add('visible'));
                }
            },
            { threshold: 0.08 }
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, []);
    return ref;
}

export default function Features() {
    const ref = useScrollAnimation();

    return (
        <section id="features" ref={ref} className="relative py-32 overflow-hidden">
            {/* Subtle dot pattern */}
            <div className="absolute inset-0 bg-pattern-dots pointer-events-none opacity-60" />

            <div className="relative max-w-7xl mx-auto px-6">
                {/* Header — slides up */}
                <div className="text-center mb-20 animate-in">
                    <span className="inline-block text-xs font-semibold tracking-widest uppercase text-accent-400 border border-accent-500/20 px-4 py-2 rounded-full mb-6"
                        style={{ background: 'rgba(52,211,153,0.06)' }}>
                        Why CertiLedger?
                    </span>
                    <h2 className="text-4xl md:text-5xl font-extrabold text-white mb-6">
                        Built for <span className="gradient-text">Security</span> &amp; Trust
                    </h2>
                    <p className="text-slate-400 text-lg max-w-2xl mx-auto">
                        Every design decision in CertiLedger is driven by one goal: making certificate fraud impossible.
                    </p>
                </div>

                {/* Feature Grid — alternating left/right/scale animations per row */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    {features.map((f, i) => {
                        // Row 1 (0,1,2): left, up, right — Row 2 (3,4,5): left, up, right
                        const animClass =
                            i % 3 === 0 ? 'animate-in-left' :
                                i % 3 === 2 ? 'animate-in-right' :
                                    'animate-in';

                        return (
                            <div
                                key={f.title}
                                className={`${animClass} animate-in-delay-${(i % 3) + 1} group relative rounded-2xl p-6 border border-white/5 transition-all duration-300 ${f.hoverBorder} hover:-translate-y-1`}
                                style={{ background: 'rgba(255,255,255,0.022)', backdropFilter: 'blur(12px)' }}
                            >
                                {/* Tag */}
                                <span
                                    className={`inline-block text-xs font-semibold tracking-wide uppercase px-3 py-1 rounded-full border mb-5 ${f.tagColor}`}
                                    style={{ background: f.tagBg }}
                                >
                                    {f.tag}
                                </span>

                                {/* Icon — flat colored, no gradient fill */}
                                <div className={`w-11 h-11 rounded-xl border border-white/8 flex items-center justify-center mb-4 ${f.iconColor} transition-transform duration-300 group-hover:scale-110`}
                                    style={{ background: 'rgba(255,255,255,0.04)' }}>
                                    {f.icon}
                                </div>

                                <h3 className="text-base font-bold text-white mb-2">{f.title}</h3>
                                <p className="text-slate-400 text-sm leading-relaxed">{f.description}</p>
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
