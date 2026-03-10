import React, { useEffect, useRef } from 'react';

export default function CTA() {
    const ref = useRef(null);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    el.querySelectorAll('.animate-in').forEach(c => c.classList.add('visible'));
                }
            },
            { threshold: 0.2 }
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, []);

    return (
        <section id="verify" ref={ref} className="relative py-32 overflow-hidden">
            {/* Gradient bg */}
            <div className="absolute inset-0 pointer-events-none">
                <div className="orb w-[600px] h-[400px] bg-brand-600/15 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" />
            </div>
            <div className="absolute inset-0 pointer-events-none"
                style={{
                    background: 'radial-gradient(ellipse 80% 60% at 50% 50%, rgba(99,102,241,0.08) 0%, transparent 70%)',
                }}
            />

            <div className="relative max-w-5xl mx-auto px-6">
                {/* Main CTA Card */}
                <div className="animate-in glass-card rounded-3xl p-10 md:p-16 border border-brand-500/20 text-center relative overflow-hidden">
                    {/* Decorative corner light */}
                    <div className="absolute top-0 right-0 w-64 h-64 bg-brand-500/10 rounded-full blur-3xl pointer-events-none" />
                    <div className="absolute bottom-0 left-0 w-64 h-64 bg-accent-500/10 rounded-full blur-3xl pointer-events-none" />

                    <div className="relative z-10">
                        <div className="inline-flex items-center gap-2 text-xs font-semibold tracking-widest uppercase text-brand-400 bg-brand-500/10 border border-brand-500/20 px-4 py-2 rounded-full mb-8">
                            <span className="w-2 h-2 rounded-full bg-brand-400 animate-pulse" />
                            Ready to Get Started?
                        </div>

                        <h2 className="text-4xl md:text-6xl font-extrabold text-white mb-6">
                            Eliminate Certificate <br />
                            <span className="gradient-text">Fraud Forever</span>
                        </h2>

                        <p className="text-slate-400 text-lg max-w-2xl mx-auto mb-12 leading-relaxed">
                            Join universities and institutions already using CertiLedger to issue tamper-proof credentials
                            on the Ethereum blockchain. Verification takes seconds, trust lasts forever.
                        </p>

                        {/* CTA Buttons */}
                        <div className="flex flex-col sm:flex-row justify-center gap-4 mb-12">
                            <a href="#" className="btn-primary text-base px-10 py-4 justify-center w-full sm:w-auto">
                                <span className="relative z-10">Issue Certificate Now</span>
                                <svg className="w-5 h-5 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </a>
                            <a href="#" className="btn-secondary text-base px-10 py-4 justify-center w-full sm:w-auto">
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                Verify a Certificate
                            </a>
                        </div>

                        {/* Quick verify input */}
                        <div className="max-w-xl mx-auto animate-in animate-in-delay-2">
                            <p className="text-sm text-slate-500 mb-3">Quick Verification</p>
                            <div className="flex gap-2">
                                <input
                                    type="text"
                                    placeholder="Enter Certificate ID (e.g. CERT-2024-0x7F3A)"
                                    className="flex-1 bg-white/5 border border-white/10 rounded-xl px-5 py-3.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-brand-500/50 focus:bg-white/8 transition-all duration-200 font-mono"
                                />
                                <button className="btn-primary px-5 py-3.5 flex-shrink-0">
                                    <svg className="w-5 h-5 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
