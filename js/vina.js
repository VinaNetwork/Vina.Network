// ============================================================================
// File: js/vina.js
// Description: Global JavaScript functions used across the Vina Network website.
// Created by: Vina Network
// ============================================================================

// Debounce function to limit the frequency of function execution
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Typewriter effect for simulating text typing animation
function typewriterEffect(element, text, speed = 50, delay = 200) {
    console.log('Starting typewriterEffect for:', element, text); // Debugging log
    let i = 0;
    element.textContent = ''; // Clear existing content
    element.style.borderRight = '2px solid #00d4ff'; // Simulate blinking cursor

    setTimeout(() => {
        function type() {
            if (i < text.length) {
                element.textContent += text[i];
                i++;
                setTimeout(type, speed); // Continue typing next character
            } else {
                console.log('Typewriter effect completed'); // Debugging log
                // Toggle blinking cursor indefinitely after typing completes
                setInterval(() => {
                    element.style.borderRight = element.style.borderRight ? '' : '2px solid #00d4ff';
                }, 750);
            }
        }
        type();
    }, delay);
}

// DOM Ready: Activate fade-in animation and typewriter effect
document.addEventListener('DOMContentLoaded', () => {
    const fadeElements = document.querySelectorAll('.fade-in'); // Elements with fade-in animation
    const heroP = document.querySelector('.hero-content p');    // Hero section paragraph
    console.log('Hero P element:', heroP); // Debug

    if (heroP) {
        // Start typewriter effect on hero section text
        typewriterEffect(heroP, '"Simplifying Crypto. Unlocking Web3"', 50, 200);
    } else {
        console.error('Hero P element not found'); // Debug error log
    }

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

// Back to Top Button Logic
const backToTopButton = document.getElementById("back-to-top");

// Show the button only after scrolling down 100px
window.onscroll = function() {
    if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
        backToTopButton.classList.add("show");
    } else {
        backToTopButton.classList.remove("show");
    }
};

// Scroll smoothly to top when button is clicked
backToTopButton.addEventListener("click", function() {
    window.scrollTo({
        top: 0,
        behavior: "smooth"
    });
});
