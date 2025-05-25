// Debounce function to limit the frequency of function execution
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Typewriter effect function
function typewriterEffect(element, text, speed = 50) {
    let i = 0;
    element.textContent = ''; // Clear initial content
    element.style.borderRight = '2px solid #00d4ff'; // Add blinking cursor

    function type() {
        if (i < text.length) {
            element.textContent += text[i];
            i++;
            setTimeout(type, speed);
        } else {
            // Start blinking cursor animation
            setInterval(() => {
                element.style.borderRight = element.style.borderRight ? '' : '2px solid #00d4ff';
            }, 750); // Match CSS blinkCursor timing
        }
    }
    type();
}

// Activate fade-in and typewriter for initial elements when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const fadeElements = document.querySelectorAll('.fade-in');
    const heroP = document.querySelector('.hero-content p');
    const isMobile = window.innerWidth <= 768;

    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const delay = parseInt(entry.target.getAttribute('data-delay') || 0);
                setTimeout(() => {
                    entry.target.classList.add('visible');
                    // Apply typewriter effect for hero p on mobile
                    if (isMobile && entry.target === heroP) {
                        typewriterEffect(heroP, '"Simplifying Crypto. Unlocking Web3"');
                    }
                    observer.unobserve(entry.target); // Stop observing after displaying
                }, delay);
            }
        });
    }, {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    });

    // Observe all fade-in elements
    fadeElements.forEach(element => {
        observer.observe(element);
    });

    // Ensure the first element is displayed immediately
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

    // Apply typewriter effect for hero p on desktop
    if (!isMobile && heroP) {
        heroP.style.borderRight = '2px solid #00d4ff';
        setTimeout(() => {
            heroP.style.borderRight = 'none'; // Remove cursor after typewriter completes
        }, 4000); // Match desktop typewriter duration (4s)
    }
});

// Change navbar style on scroll with debounce
document.addEventListener('scroll', debounce(() => {
    if (window.scrollY > 50) {
        document.querySelector('.navbar').classList.add('scrolled');
    } else {
        document.querySelector('.navbar').classList.remove('scrolled');
    }
}, 100));

// Burger menu toggle
const burger = document.querySelector('.burger');
const navLinks = document.querySelector('.nav-links');
burger.addEventListener('click', () => {
    burger.classList.toggle('active');
    navLinks.classList.toggle('active');
});

// Active state for nav links
const navLinksItems = document.querySelectorAll('.nav-link');
navLinksItems.forEach(link => {
    link.addEventListener('click', (e) => {
        if (!link.classList.contains('dropdown-toggle')) {
            navLinksItems.forEach(item => item.classList.remove('active'));
            link.classList.add('active');
            if (navLinks.classList.contains('active')) {
                burger.classList.remove('active');
                navLinks.classList.remove('active');
            }
        }
    });
});

// Toggle dropdown menu
const dropdowns = document.querySelectorAll('.dropdown');
dropdowns.forEach(dropdown => {
    const toggle = dropdown.querySelector('.dropdown-toggle');
    const content = dropdown.querySelector('.dropdown-menu');

    toggle.addEventListener('click', (e) => {
        e.preventDefault();
        content.classList.toggle('active');
        dropdowns.forEach(d => {
            if (d !== dropdown) {
                d.querySelector('.dropdown-menu').classList.remove('active');
            }
        });
    });
});

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        if (!dropdown.contains(e.target)) {
            dropdown.querySelector('.dropdown-menu').classList.remove('active');
        }
    });
});

// Get the Back to Top button
const backToTopButton = document.getElementById("back-to-top");

// Show button when scrolling down 100px
window.onscroll = function() {
    if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
        backToTopButton.classList.add("show");
    } else {
        backToTopButton.classList.remove("show");
    }
};

// Smooth scroll to top when clicking the button
backToTopButton.addEventListener("click", function() {
    window.scrollTo({
        top: 0,
        behavior: "smooth"
    });
});
