import React, { useEffect, useRef } from 'react';

function useScrollReveal(threshold = 0.08) {
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

const steps = [
  {
    n: '01',
    title: 'Institution issues',
    body: 'An authorized university wallet calls the smart contract\'s issueCertificate function. The certificate data — name, course, date — is submitted as a transaction.',
  },
  {
    n: '02',
    title: 'Hash recorded on-chain',
    body: 'A SHA-256 hash of all certificate fields is computed and stored on the Ethereum blockchain. The raw data stays in the database; the proof lives on-chain forever.',
  },
  {
    n: '03',
    title: 'Student gets a verifiable ID',
    body: 'A unique certificate ID is generated. The student can share this ID — or a link — with any employer, institution, or verifier worldwide.',
  },
  {
    n: '04',
    title: 'Verification is public, instant',
    body: 'Anyone queries the smart contract with a certificate ID. The contract compares hashes and returns a cryptographic true/false — no login, no middleman.',
  },
];

export default function HowItWorks() {
  const ref = useScrollReveal();

  return (
    <section id="how-it-works" ref={ref} style={{ borderTop: '1px solid #1a1a1a', padding: '7rem 2rem' }}>
      <div className="max-w-7xl mx-auto">
        {/* Section label */}
        <p className="label sr" style={{ marginBottom: '4rem' }}>The protocol</p>

        {/* Steps — horizontal ruled list, not cards */}
        <div>
          {steps.map((step, i) => (
            <div
              key={step.n}
              className={`sr sr-d${i + 1}`}
              style={{
                display: 'grid',
                gridTemplateColumns: '5rem 1fr 1.8fr',
                gap: '3rem',
                alignItems: 'start',
                padding: '2.5rem 0',
                borderBottom: '1px solid #1a1a1a',
              }}
            >
              {/* Step number */}
              <span
                className="mono"
                style={{ color: '#2a2a2a', fontSize: '0.75rem', paddingTop: '0.2rem' }}
              >
                {step.n}
              </span>

              {/* Title */}
              <h3
                style={{
                  fontSize: 'clamp(1.25rem, 2.5vw, 1.75rem)',
                  fontWeight: 600,
                  letterSpacing: '-0.02em',
                  lineHeight: 1.2,
                  color: '#fff',
                }}
              >
                {step.title}
              </h3>

              {/* Body */}
              <p
                style={{
                  color: '#666',
                  fontSize: '0.95rem',
                  lineHeight: 1.7,
                  maxWidth: '52ch',
                }}
              >
                {step.body}
              </p>
            </div>
          ))}
        </div>

        {/* Chain-tip terminal block */}
        <div className="sr sr-d5" style={{ marginTop: '4rem' }}>
          <div
            className="mono"
            style={{
              background: '#060606',
              border: '1px solid #1a1a1a',
              padding: '1.5rem 2rem',
              display: 'inline-block',
            }}
          >
            <span style={{ color: '#333' }}>$ </span>
            <span style={{ color: '#aaa' }}>certiledger verify </span>
            <span style={{ color: '#666' }}>CERT-ETH-2024-0x7F3A</span>
            <br />
            <span style={{ color: '#2a7', marginLeft: '1rem' }}>✓ valid · block #19,847,203 · immutable</span>
          </div>
        </div>
      </div>
    </section>
  );
}
