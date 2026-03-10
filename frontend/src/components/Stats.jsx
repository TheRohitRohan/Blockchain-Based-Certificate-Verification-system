import React, { useEffect, useRef, useState } from 'react';

// Realistic, honest stats for a project/demo
const stats = [
    { end: 3, suffix: '+', label: 'Institutions Onboarded', sublabel: 'Universities using CertiLedger' },
    { end: 120, suffix: '+', label: 'Certificates Issued', sublabel: 'On local Ethereum testnet' },
    { end: 100, suffix: '%', label: 'Tamper-Proof Rate', sublabel: 'Zero altered certificates detected' },
    { end: 0, suffix: '', label: 'Fraudulent Certs', sublabel: 'Blockchain ensures zero fraud' },
];

function AnimatedCounter({ end, suffix, duration = 1800, visible }) {
    const [value, setValue] = useState(0);
    const startRef = useRef(null);

    useEffect(() => {
        if (!visible) return;
        startRef.current = performance.now();
        const step = (ts) => {
            const elapsed = ts - startRef.current;
            const progress = Math.min(elapsed / duration, 1);
            // Expo ease-out
            const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
            setValue(end * eased);
            if (progress < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }, [visible, end, duration]);

    const display = end >= 100
        ? Math.floor(value).toLocaleString()
        : value.toFixed(end % 1 !== 0 ? 1 : 0);

    return <span className="counter-value">{display}{suffix}</span>;
}

function useScrollSection() {
    const ref = useRef(null);
    const [visible, setVisible] = useState(false);
    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setVisible(true);
                    el.querySelectorAll(
                        '.animate-in, .animate-in-left, .animate-in-right, .animate-in-scale'
                    ).forEach(c => c.classList.add('visible'));
                }
            },
            { threshold: 0.1 }
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, []);
    return [ref, visible];
}

export default function Stats() {
    const [ref, visible] = useScrollSection();

    return (
        <section id="about" ref={ref} className="relative py-32 overflow-hidden">
            {/* Dot pattern background */}
            <div className="absolute inset-0 bg-pattern-dots pointer-events-none" />

            <div className="relative max-w-7xl mx-auto px-6">
                {/* Heading — slides up */}
                <div className="text-center mb-20 animate-in">
                    <span className="inline-block text-xs font-semibold tracking-widest uppercase text-brand-400 border border-brand-500/20 px-4 py-2 rounded-full mb-6"
                        style={{ background: 'rgba(99,102,241,0.06)' }}>
                        By The Numbers
                    </span>
                    <h2 className="text-4xl md:text-5xl font-extrabold text-white mb-6">
                        Built for <span className="gradient-text">Integrity</span>
                    </h2>
                    <p className="text-slate-400 text-lg max-w-2xl mx-auto">
                        CertiLedger is actively protecting academic credentials — every certificate immutably recorded on Ethereum.
                    </p>
                </div>

                {/* Stats Grid — cards scale in with stagger */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-6">
                    {stats.map((stat, i) => (
                        <div
                            key={stat.label}
                            className={`animate-in-scale animate-in-delay-${i + 1} rounded-2xl p-8 text-center border border-white/5 transition-all duration-300 hover:border-brand-500/20 hover:-translate-y-1`}
                            style={{ background: 'rgba(255,255,255,0.025)', backdropFilter: 'blur(16px)' }}
                        >
                            <div className="text-4xl lg:text-5xl font-black gradient-text mb-2">
                                <AnimatedCounter end={stat.end} suffix={stat.suffix} visible={visible} />
                            </div>
                            <div className="text-sm font-semibold text-white mb-1">{stat.label}</div>
                            <div className="text-xs text-slate-500">{stat.sublabel}</div>
                        </div>
                    ))}
                </div>

                {/* Tech strip — slides up with delay */}
                <div className="mt-20 animate-in animate-in-delay-5">
                    <div className="flex items-center gap-4 mb-8">
                        <div className="flex-1 h-px" style={{ background: 'linear-gradient(90deg, transparent, rgba(255,255,255,0.06))' }} />
                        <span className="text-xs text-slate-600 uppercase tracking-widest font-medium">Powered By</span>
                        <div className="flex-1 h-px" style={{ background: 'linear-gradient(270deg, transparent, rgba(255,255,255,0.06))' }} />
                    </div>
                    <div className="flex flex-wrap justify-center gap-4 items-center">
                        {[
                            { label: 'Ethereum', icon: '⟠' },
                            { label: 'Solidity', icon: '◈' },
                            { label: 'Truffle', icon: '🍄' },
                            { label: 'Web3.js', icon: '🌐' },
                            { label: 'SHA-256', icon: '#' },
                            { label: 'Ganache', icon: '🔷' },
                        ].map(tech => (
                            <div
                                key={tech.label}
                                className="flex items-center gap-2 px-5 py-3 rounded-xl text-slate-400 hover:text-white border border-white/5 hover:border-brand-500/20 transition-all duration-200 cursor-default"
                                style={{ background: 'rgba(255,255,255,0.02)' }}
                            >
                                <span className="text-lg">{tech.icon}</span>
                                <span className="text-sm font-medium">{tech.label}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
