import React, { useEffect, useRef } from 'react';

function useScrollReveal(threshold = 0.06) {
  const ref = useRef(null);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const io = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          el.querySelectorAll('.sr,.sr-left,.sr-right,.sr-scale').forEach(c => c.classList.add('visible'));
        }
      },
      { threshold }
    );
    io.observe(el);
    return () => io.disconnect();
  }, []);
  return ref;
}

const properties = [
  {
    key: 'immutability',
    title: 'Immutable',
    body: 'Once a certificate is written to the blockchain, it cannot be modified, deleted, or overwritten. The record exists permanently — no administrator can erase it.',
  },
  {
    key: 'trustless',
    title: 'Trustless',
    body: 'Verification requires no trust in CertiLedger, the university, or any third party. The Ethereum network cryptographically guarantees the result.',
  },
  {
    key: 'transparent',
    title: 'Transparent',
    body: 'Every issuance and revocation is a public blockchain event. The smart contract code is open source and auditable by anyone at any time.',
  },
  {
    key: 'permissionless',
    title: 'Open to verify',
    body: 'No account, no API key, no fee. Anyone with a certificate ID can verify it directly through the smart contract — a browser is all you need.',
  },
];

export default function WhyCertiLedger() {
  const ref = useScrollReveal();

  return (
    <section id="security" ref={ref} style={{ borderTop: '1px solid #1a1a1a', padding: '7rem 2rem' }}>
      <div className="max-w-7xl mx-auto">

        {/* Two-column header */}
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: '1fr 1fr',
            gap: '4rem',
            alignItems: 'end',
            marginBottom: '5rem',
          }}
        >
          <div>
            <p className="label sr" style={{ marginBottom: '1.5rem' }}>Core properties</p>
            <h2
              className="sr sr-d1"
              style={{
                fontSize: 'clamp(2.25rem, 5vw, 4rem)',
                fontWeight: 700,
                letterSpacing: '-0.03em',
                lineHeight: 1,
                color: '#fff',
              }}
            >
              Why blockchain?
            </h2>
          </div>
          <p
            className="sr sr-d2 hidden md:block"
            style={{ color: '#555', fontSize: '1rem', lineHeight: 1.7, maxWidth: '44ch', alignSelf: 'flex-end' }}
          >
            Traditional certificate databases are centralized, mutable, and opaque.
            A blockchain-backed approach removes those failure points entirely.
          </p>
        </div>

        {/* Property grid — 2×2, text only, divided by rules */}
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(2, 1fr)',
            borderTop: '1px solid #1a1a1a',
            borderLeft: '1px solid #1a1a1a',
          }}
        >
          {properties.map((p, i) => (
            <div
              key={p.key}
              className={`sr sr-d${i + 1}`}
              style={{
                padding: '3rem 2.5rem',
                borderBottom: '1px solid #1a1a1a',
                borderRight: '1px solid #1a1a1a',
              }}
            >
              {/* Index */}
              <span className="mono" style={{ color: '#2a2a2a', display: 'block', marginBottom: '2rem' }}>
                0{i + 1}
              </span>

              {/* Title — heavier weight */}
              <h3
                style={{
                  fontSize: '1.5rem',
                  fontWeight: 700,
                  letterSpacing: '-0.02em',
                  marginBottom: '1rem',
                  color: '#fff',
                }}
              >
                {p.title}
              </h3>

              {/* Body */}
              <p style={{ color: '#555', fontSize: '0.9rem', lineHeight: 1.7 }}>
                {p.body}
              </p>
            </div>
          ))}
        </div>

        {/* Comparison strip */}
        <div
          className="sr sr-d5"
          style={{ marginTop: '4rem', display: 'flex', gap: '0', borderTop: '1px solid #1a1a1a' }}
        >
          {[
            { label: 'Traditional DB', val: 'Mutable · Centralized · Opaque', dim: true },
            { label: 'CertiLedger',    val: 'Immutable · Decentralized · Transparent', dim: false },
          ].map(({ label, val, dim }) => (
            <div
              key={label}
              style={{
                flex: 1,
                padding: '1.75rem 0',
                borderRight: '1px solid #1a1a1a',
              }}
            >
              <div className="label" style={{ marginBottom: '0.5rem' }}>{label}</div>
              <div
                className="mono"
                style={{ color: dim ? '#333' : '#888', fontSize: '0.8rem' }}
              >
                {val}
              </div>
            </div>
          ))}
          <div style={{ width: '1px' }} /> {/* close border */}
        </div>
      </div>
    </section>
  );
}
