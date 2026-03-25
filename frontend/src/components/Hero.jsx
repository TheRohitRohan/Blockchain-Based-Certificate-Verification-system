import React, { useEffect, useRef, useState } from 'react';

/* Animated terminal-style certificate block */
function CertBlock() {
  const [tick, setTick] = useState(0);
  useEffect(() => {
    const t = setInterval(() => setTick(n => n + 1), 2400);
    return () => clearInterval(t);
  }, []);

  const hashes = [
    '0xa3f5e2c71d9b4f8a2c6d0e1b3f5a',
    '0x7f3a9e2b1c4d8f6a0e1b2c3d4e5f',
    '0xb8d4c1e9f2a5b3c7d0e4f1a8b2c5',
  ];
  const hash = hashes[tick % hashes.length];

  return (
    <div
      className="sr-right mono"
      style={{
        background: '#0a0a0a',
        border: '1px solid #1f1f1f',
        padding: '1.5rem',
        width: '100%',
        maxWidth: '380px',
      }}
    >
      {/* Header row */}
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '1rem' }}>
        <span style={{ color: '#444', fontSize: '0.65rem', letterSpacing: '0.15em', textTransform: 'uppercase' }}>
          Certificate
        </span>
        <span style={{ color: '#2a7' , fontSize: '0.65rem', letterSpacing: '0.08em' }}>
          ● VALID
        </span>
      </div>

      {/* Fields */}
      {[
        { k: 'id',         v: 'CERT-ETH-2024-0x7F3A' },
        { k: 'student',    v: 'Rohit Sharma' },
        { k: 'course',     v: 'B.Tech Computer Science' },
        { k: 'issued_by',  v: 'IIT Bombay' },
        { k: 'block',      v: '#19,847,203' },
      ].map(({ k, v }) => (
        <div key={k} style={{ display: 'flex', gap: '1rem', marginBottom: '0.45rem' }}>
          <span style={{ color: '#444', flexShrink: 0, width: '6.5rem' }}>{k}</span>
          <span style={{ color: '#aaa' }}>{v}</span>
        </div>
      ))}

      {/* Hash row — animates between values */}
      <div style={{ marginTop: '1rem', paddingTop: '1rem', borderTop: '1px solid #1a1a1a' }}>
        <div style={{ color: '#444', fontSize: '0.65rem', marginBottom: '0.4rem', letterSpacing: '0.1em', textTransform: 'uppercase' }}>sha256</div>
        <div
          key={hash}
          style={{
            color: '#555',
            wordBreak: 'break-all',
            fontSize: '0.7rem',
            animation: 'fade 0.4s ease',
          }}
        >
          {hash}…
        </div>
      </div>
    </div>
  );
}

export default function Hero() {
  const sectionRef = useRef(null);

  useEffect(() => {
    const el = sectionRef.current;
    if (!el) return;
    // Trigger sr elements inside immediately (above-fold)
    el.querySelectorAll('.sr,.sr-left,.sr-right,.sr-scale').forEach(c => c.classList.add('visible'));
  }, []);

  return (
    <section
      ref={sectionRef}
      style={{
        minHeight: '100svh',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'flex-end',
        padding: '0 2rem 5rem',
        paddingTop: '6rem',
        position: 'relative',
        background: '#000',
        overflow: 'hidden',
      }}
    >
      {/* Faint dot grid */}
      <div
        aria-hidden
        style={{
          position: 'absolute',
          inset: 0,
          backgroundImage:
            'radial-gradient(circle, #1c1c1c 1px, transparent 1px)',
          backgroundSize: '36px 36px',
          pointerEvents: 'none',
          opacity: 0.7,
        }}
      />

      {/* Horizontal scan line */}
      <div
        aria-hidden
        style={{
          position: 'absolute',
          left: 0,
          right: 0,
          height: '1px',
          background:
            'linear-gradient(90deg, transparent 0%, #ffffff08 20%, #ffffff14 50%, #ffffff08 80%, transparent 100%)',
          animation: 'scan 6s linear infinite',
          pointerEvents: 'none',
        }}
      />

      <div
        className="max-w-7xl mx-auto w-full"
        style={{
          display: 'grid',
          gridTemplateColumns: '1fr auto',
          gap: '4rem',
          alignItems: 'flex-end',
          position: 'relative',
          zIndex: 1,
        }}
      >
        {/* Left — large type */}
        <div>
          {/* Label */}
          <p className="label sr sr-d1" style={{ marginBottom: '2rem' }}>
            Ethereum · Smart Contracts · Open Source
          </p>

          {/* Display headline — variable weight */}
          <h1
            className="sr sr-d2"
            style={{
              fontSize: 'clamp(3.5rem, 9vw, 8.5rem)',
              fontWeight: 800,
              lineHeight: 0.9,
              letterSpacing: '-0.04em',
              fontVariationSettings: '"wdth" 100',
              marginBottom: '2.5rem',
            }}
          >
            Certificates
            <br />
            <span style={{ fontWeight: 300, color: '#555', letterSpacing: '-0.03em' }}>
              on&#8209;chain.
            </span>
          </h1>

          {/* Sub */}
          <p
            className="sr sr-d3"
            style={{
              fontSize: '1.125rem',
              color: '#666',
              maxWidth: '44ch',
              lineHeight: 1.65,
              fontWeight: 400,
              marginBottom: '3rem',
            }}
          >
            A blockchain protocol that issues and stores academic certificates
            as immutable records on Ethereum. Tamper-proof by design.
            Verifiable by anyone.
          </p>

          {/* Actions */}
          <div className="sr sr-d4" style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
            <a href="#" className="btn btn-white">Issue a certificate</a>
            <a href="#verify" className="btn btn-ghost">Verify →</a>
          </div>
        </div>

        {/* Right — cert block */}
        <div className="hidden lg:block" style={{ paddingBottom: '0.25rem' }}>
          <CertBlock />
        </div>
      </div>

      {/* Bottom tags row */}
      <div
        className="max-w-7xl mx-auto w-full sr"
        style={{
          display: 'flex',
          gap: '2rem',
          marginTop: '4rem',
          paddingTop: '2rem',
          borderTop: '1px solid #1a1a1a',
          position: 'relative',
          zIndex: 1,
        }}
      >
        {[
          { label: 'Network', value: 'Ethereum' },
          { label: 'Contract', value: 'CertificateRegistry.sol' },
          { label: 'Status', value: 'In Development' },
          { label: 'License', value: 'MIT' },
        ].map(({ label, value }) => (
          <div key={label}>
            <div className="label" style={{ marginBottom: '0.3rem' }}>{label}</div>
            <div className="mono" style={{ color: '#888' }}>{value}</div>
          </div>
        ))}
      </div>
    </section>
  );
}
