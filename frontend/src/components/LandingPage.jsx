import React from 'react';
import Navbar from './Navbar';
import Hero from './Hero';
import HowItWorks from './HowItWorks';
import Features from './Features';
import Stats from './Stats';
import CTA from './CTA';
import Footer from './Footer';

export default function LandingPage() {
  return (
    <div className="noise" style={{ background: 'var(--bg)', minHeight: '100vh' }}>
      <Navbar />
      <main>
        <Hero />
        <HowItWorks />
        <Features />
        <Stats />
        <CTA />
      </main>
      <Footer />
    </div>
  );
}
