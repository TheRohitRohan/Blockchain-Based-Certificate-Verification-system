import React from 'react';
import Navbar from './Navbar';
import Hero from './Hero';
import HowItWorks from './HowItWorks';
import Features from './Features';
import Stats from './Stats';
import Security from './Security';
import CTA from './CTA';
import Footer from './Footer';

export default function LandingPage() {
    return (
        <div className="min-h-screen bg-dark-900 text-white overflow-x-hidden">
            <Navbar />
            <main>
                <Hero />
                <HowItWorks />
                <Features />
                <Security />
                <Stats />
                <CTA />
            </main>
            <Footer />
        </div>
    );
}
