import React, { useEffect, useRef, useState } from 'react';

function useScrollReveal(threshold = 0.08) {
  const ref = useRef(null);
  const [triggered, setTriggered] = useState(false);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const io = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          el.querySelectorAll('.sr,.sr-left,.sr-right,.sr-scale').forEach(c => c.classList.add('visible'));
          setTriggered(true);
        }
      },
      { threshold }
    );
    io.observe(el);
    return () => io.disconnect();
  }, []);
  return [ref, triggered];
}

/* Animated counter — only runs once section is visible */
function Counter({ to, suffix = '', decimals = 0, triggered }) {
  const [val, setVal] = useState(0);
  const raf = useRef(null);

  useEffect(() => {
    if (!triggered) return;
    const start = performance.now();
    const duration = 1600;
    const step = (ts) => {
      const p = Math.min((ts - start) / duration, 1);
      const eased = 1 - Math.pow(1 - p, 3);
      setVal(to * eased);
      if (p < 1) raf.current = requestAnimationFrame(step);
    };
    raf.current = requestAnimationFrame(step);
    return () => cancelAnimationFrame(raf.current);
  }, [triggered, to]);

  const display = decimals > 0
    ? val.toFixed(decimals)
    : Math.floor(val).toLocaleString();

  return <>{display}{suffix}</>;
}

const data = [
  { label: 'Lines of Solidity',   to: 280, suffix: '' },
  { label: 'Smart contract fns',  to: 6,   suffix: '' },
  { label: 'Tamper-proof',        to: 100, suffix: '%' },
  { label: 'Fraudulent certs',    to: 0,   suffix: '' },
];

export default function Stats() {
  const [ref, triggered] = useScrollReveal();

  return (
    <section id='stats' ref={ref} style={{ borderTop: '1px solid var(--border)', padding: '6rem 2rem' }}>
      <div className="max-w-7xl mx-auto">
        {/* Label */}
        <p className="label sr" style={{ marginBottom: '3.5rem' }}>By the numbers</p>

        {/* Stat row — horizontal strip with vertical dividers */}
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(4, 1fr)',
            borderTop: '1px solid var(--border)',
          }}
        >
          {data.map(({ label, to, suffix }, i) => (
            <div
              key={label}
              className={`sr sr-scale sr-d${i + 1}`}
              style={{
                padding: '2.5rem 0',
                paddingRight: '2rem',
                borderRight: i < data.length - 1 ? '1px solid var(--border)' : 'none',
                paddingLeft: i > 0 ? '2rem' : 0,
              }}
            >
              {/* Big number */}
              <div
                style={{
                  fontSize: 'clamp(2.5rem, 5vw, 4.5rem)',
                  fontWeight: 800,
                  letterSpacing: '-0.04em',
                  lineHeight: 1,
                  color: 'var(--text)',
                  marginBottom: '0.75rem',
                  fontVariationSettings: '"wdth" 100',
                }}
              >
                <Counter to={to} suffix={suffix} triggered={triggered} />
              </div>
              <div className="label">{label}</div>
            </div>
          ))}
        </div>

        {/* Scrolling tech marquee */}
        <div
          className="sr"
          style={{
            marginTop: '4rem',
            paddingTop: '2rem',
            borderTop: '1px solid var(--border)',
            overflow: 'hidden',
          }}
        >
          <div className="marquee-track mono" style={{ color: 'var(--text3)', fontSize: '0.7rem', letterSpacing: '0.12em' }}>
            {[
              'ETHEREUM', '·', 'SOLIDITY ^0.8', '·', 'TRUFFLE', '·',
              'GANACHE', '·', 'WEB3.PHP', '·', 'SHA-256', '·', 'ERC COMPATIBLE', '·',
              'ETHEREUM', '·', 'SOLIDITY ^0.8', '·', 'TRUFFLE', '·',
              'GANACHE', '·', 'WEB3.PHP', '·', 'SHA-256', '·', 'ERC COMPATIBLE', '·',
            ].map((t, i) => (
              <span key={i} style={{ marginRight: t === '·' ? '1.5rem' : '1.5rem' }}>{t}</span>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
