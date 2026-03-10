import React, { useEffect, useRef } from 'react';

const steps = [
    {
        number: '01',
        icon: (
            <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
        ),
        title: 'University Issues Certificate',
        description:
            'An authorized institution submits the certificate details to our smart contract. The data is hashed and recorded permanently on Ethereum.',
        color: 'bg-brand-600',
        border: 'border-brand-500/20',
    },
    {
        number: '02',
        icon: (
            <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
        ),
        title: 'SHA-256 Hash Stored On-Chain',
        description:
            'A SHA-256 cryptographic hash is generated from every certificate field and stored immutably on the Ethereum blockchain — unalterable after issuance.',
        color: 'bg-violet-700',
        border: 'border-violet-500/20',
    },
    {
        number: '03',
        icon: (
            <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
        ),
        title: 'Student Receives Secure Link',
        description:
            'Students receive a unique certificate ID and verification link they can share with employers or institutions worldwide.',
        color: 'bg-teal-700',
        border: 'border-teal-500/20',
    },
    {
        number: '04',
        icon: (
            <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        ),
        title: 'Anyone Can Verify Instantly',
        description:
            'Enter a certificate ID to query the blockchain directly. A cryptographic proof of authenticity is returned within seconds — no account required.',
        color: 'bg-accent-600',
        border: 'border-accent-500/20',
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

export default function HowItWorks() {
    const ref = useScrollAnimation();

    return (
        <section id="how-it-works" ref={ref} className="relative py-32 overflow-hidden">
            {/* Diagonal lines pattern */}
            <div className="absolute inset-0 bg-pattern-lines pointer-events-none opacity-100" />

            <div className="relative max-w-7xl mx-auto px-6">
                {/* Header — slides up */}
                <div className="text-center mb-20 animate-in">
                    <span className="inline-block text-xs font-semibold tracking-widest uppercase text-brand-400 border border-brand-500/20 px-4 py-2 rounded-full mb-6"
                        style={{ background: 'rgba(99,102,241,0.06)' }}>
                        The Process
                    </span>
                    <h2 className="text-4xl md:text-5xl font-extrabold text-white mb-6">
                        How It <span className="gradient-text">Works</span>
                    </h2>
                    <p className="text-slate-400 text-lg max-w-2xl mx-auto">
                        From issuance to verification, every step is secured by Ethereum smart contracts.
                    </p>
                </div>

                {/* Steps — alternating left/right on desktop, up on mobile */}
                <div className="relative">
                    {/* Connector line */}
                    <div className="hidden lg:block absolute top-16 left-0 right-0 h-px"
                        style={{ background: 'linear-gradient(90deg, transparent, rgba(99,102,241,0.2), rgba(99,102,241,0.2), transparent)' }} />

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-8">
                        {steps.map((step, i) => {
                            const dir = i % 2 === 0 ? 'animate-in-left' : 'animate-in-right';
                            return (
                                <div
                                    key={step.number}
                                    className={`${dir} animate-in-delay-${i + 1} relative group`}
                                >
                                    {/* Chain dot on connector */}
                                    <div className="hidden lg:flex absolute -top-2 left-1/2 -translate-x-1/2 w-4 h-4 rounded-full border-2 border-brand-500 z-10 chain-dot"
                                        style={{ background: '#030712' }} />

                                    <div className="rounded-2xl p-6 h-full border border-white/5 transition-all duration-300 hover:border-brand-500/20 hover:-translate-y-1"
                                        style={{ background: 'rgba(255,255,255,0.025)', backdropFilter: 'blur(16px)' }}>
                                        {/* Large step number */}
                                        <div className="text-5xl font-black mb-4 select-none"
                                            style={{ color: 'rgba(99,102,241,0.12)' }}>
                                            {step.number}
                                        </div>

                                        {/* Icon box — solid color, no gradient */}
                                        <div className={`w-12 h-12 rounded-xl ${step.color} flex items-center justify-center text-white mb-5 transition-transform duration-300 group-hover:scale-110`}>
                                            {step.icon}
                                        </div>

                                        <h3 className="text-base font-bold text-white mb-2">{step.title}</h3>
                                        <p className="text-slate-400 text-sm leading-relaxed">{step.description}</p>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Blockchain hash terminal — scales in */}
                <div className="mt-16 animate-in-scale animate-in-delay-5">
                    <div className="rounded-2xl p-6 border border-brand-500/15 max-w-3xl mx-auto"
                        style={{ background: 'rgba(255,255,255,0.025)', backdropFilter: 'blur(16px)' }}>
                        <div className="flex items-center gap-2 mb-4">
                            <div className="w-3 h-3 rounded-full bg-red-500/60" />
                            <div className="w-3 h-3 rounded-full bg-yellow-500/60" />
                            <div className="w-3 h-3 rounded-full bg-accent-500/60" />
                            <span className="ml-2 text-xs text-slate-500 font-mono">ethereum-mainnet • block #19,847,203</span>
                        </div>
                        <div className="space-y-2 font-mono text-sm">
                            <div className="flex gap-3">
                                <span className="text-brand-400 flex-shrink-0">txHash:</span>
                                <span className="text-slate-300 truncate">0x7f3a9e2b1c4d8f6a0e1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e</span>
                            </div>
                            <div className="flex gap-3">
                                <span className="text-brand-400 flex-shrink-0">certHash:</span>
                                <span className="text-accent-400 truncate">a3f5e2c71d9b4f8a2c6d0e1b3f5a7c9e1d3b5f7a9c1e3b5d7f9a1c3e5b7d9f1</span>
                            </div>
                            <div className="flex gap-3">
                                <span className="text-brand-400 flex-shrink-0">status:</span>
                                <span className="text-accent-400">✓ VALID &amp; IMMUTABLE</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
