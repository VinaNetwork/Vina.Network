// ============================================================================
// File: contact/contact.js
// Description: Contact JavaScript functions.
// Created by: Vina Network
// ============================================================================

// DOM Ready
document.addEventListener('DOMContentLoaded', () => {
    // Activate fade-in animation and typewriter effect
    const fadeElements = document.querySelectorAll('.fade-in');
    // Initialize IntersectionObserver for fade-in animations
    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const delay = parseInt(entry.target.getAttribute('data-delay') || 0);
                setTimeout(() => {
                    entry.target.classList.add('visible'); // Trigger CSS transition
                    observer.unobserve(entry.target);      // Only animate once
                }, delay);
            }
        });
    }, {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    });

    // Apply observer to all fade-in elements
    fadeElements.forEach(element => {
        observer.observe(element);
    });

    // If the first fade-in element is already in view, show it immediately
    const firstElement = fadeElements[0];
    if (firstElement) {
        const rect = firstElement.getBoundingClientRect();
        const windowHeight = window.innerHeight;
        if (rect.top >= 0 && rect.top < windowHeight) {
            const delay = parseInt(firstElement.getAttribute('data-delay') || 0);
            setTimeout(() => {
                firstElement.classList.add('visible');
            }, delay);
        }
    }
});
