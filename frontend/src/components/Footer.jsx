import React from 'react';

const cols = {
  Protocol: ['Smart Contract', 'Certificate Issuance', 'Verification', 'Revocation'],
  Technology: ['Ethereum', 'Solidity', 'Truffle', 'Ganache'],
  Resources: ['Documentation', 'GitHub', 'API Reference', 'Changelog'],
};

export default function Footer() {
  return (
    <footer style={{ borderTop: '1px solid #1a1a1a', padding: '5rem 2rem 3rem' }}>
      <div className="max-w-7xl mx-auto">
        {/* Top row */}
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: '1.5fr repeat(3, 1fr)',
            gap: '4rem',
            paddingBottom: '4rem',
            borderBottom: '1px solid #1a1a1a',
          }}
        >
          {/* Brand */}
          <div>
            <div
              style={{
                fontSize: '1rem',
                fontWeight: 600,
                letterSpacing: '-0.01em',
                color: '#fff',
                marginBottom: '1rem',
              }}
            >
              CertiLedger
            </div>
            <p style={{ color: '#444', fontSize: '0.85rem', lineHeight: 1.7, maxWidth: '28ch' }}>
              A blockchain protocol for issuing and verifying academic certificates on Ethereum.
            </p>
            {/* Contract address */}
            <div className="mono" style={{ marginTop: '1.5rem', color: '#2a2a2a', fontSize: '0.65rem' }}>
              0xEB352b98B9CCDAb750E7a99…
            </div>
          </div>

          {/* Links */}
          {Object.entries(cols).map(([heading, links]) => (
            <div key={heading}>
              <div className="label" style={{ marginBottom: '1.25rem' }}>{heading}</div>
              <ul style={{ listStyle: 'none' }}>
                {links.map(l => (
                  <li key={l} style={{ marginBottom: '0.6rem' }}>
                    <a
                      href="#"
                      style={{ color: '#444', fontSize: '0.85rem', textDecoration: 'none', transition: 'color 0.15s' }}
                      onMouseEnter={e => (e.target.style.color = '#fff')}
                      onMouseLeave={e => (e.target.style.color = '#444')}
                    >
                      {l}
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        {/* Bottom bar */}
        <div
          style={{
            paddingTop: '2rem',
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
          }}
        >
          <span style={{ color: '#2a2a2a', fontSize: '0.8rem' }}>
            © 2024 CertiLedger · MIT License
          </span>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <span style={{ width: '6px', height: '6px', borderRadius: '50%', background: '#1c5c30', display: 'inline-block' }} />
            <span className="mono" style={{ color: '#2a2a2a', fontSize: '0.65rem' }}>Ethereum · In Development</span>
          </div>
        </div>
      </div>
    </footer>
  );
}
