import React, { useEffect, useRef } from 'react';

function useScrollReveal() {
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
      { threshold: 0.1 }
    );
    io.observe(el);
    return () => io.disconnect();
  }, []);
  return ref;
}

export default function CTA() {
  const ref = useScrollReveal();

  return (
    <section id="verify" ref={ref} style={{ borderTop: '1px solid #1a1a1a', padding: '8rem 2rem' }}>
      <div className="max-w-7xl mx-auto">
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: '1fr 1fr',
            gap: '6rem',
            alignItems: 'center',
          }}
        >
          {/* Left — large statement */}
          <div>
            <p className="label sr" style={{ marginBottom: '2rem' }}>Start now</p>
            <h2
              className="sr sr-d1"
              style={{
                fontSize: 'clamp(2.5rem, 5vw, 4.5rem)',
                fontWeight: 800,
                letterSpacing: '-0.035em',
                lineHeight: 0.95,
                color: '#fff',
                marginBottom: '2rem',
                fontVariationSettings: '"wdth" 100',
              }}
            >
              One contract.
              <br />
              <span style={{ fontWeight: 300, color: '#444' }}>
                Infinite records.
              </span>
            </h2>
            <p
              className="sr sr-d2"
              style={{ color: '#555', fontSize: '0.95rem', lineHeight: 1.7, maxWidth: '42ch', marginBottom: '2.5rem' }}
            >
              CertiLedger is open to institutions wanting to pilot blockchain-based certificate issuance.
              We're in active development — early collaborators shape the protocol.
            </p>
            <div className="sr sr-d3" style={{ display: 'flex', gap: '0.75rem' }}>
              <a href="#" className="btn btn-white">Issue a certificate</a>
              <a href="#" className="btn btn-ghost">Read the docs</a>
            </div>
          </div>

          {/* Right — verify input */}
          <div className="sr-right hidden md:block">
            <p className="label" style={{ marginBottom: '1.25rem' }}>Verify a certificate</p>
            <div
              style={{
                display: 'flex',
                borderTop: '1px solid #222',
                borderBottom: '1px solid #222',
              }}
            >
              <input
                type="text"
                placeholder="Enter certificate ID"
                style={{
                  flex: 1,
                  background: 'transparent',
                  border: 'none',
                  outline: 'none',
                  color: '#fff',
                  fontSize: '0.9rem',
                  padding: '1rem 0',
                  fontFamily: '"JetBrains Mono", monospace',
                  caretColor: '#fff',
                }}
              />
              <button
                className="btn btn-white"
                style={{ margin: '0.5rem 0', fontSize: '0.85rem', padding: '0.5rem 1.25rem' }}
              >
                Verify →
              </button>
            </div>
            <p className="mono" style={{ color: '#2a2a2a', marginTop: '1rem', fontSize: '0.7rem' }}>
              Queries live Ethereum. No account needed.
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}
