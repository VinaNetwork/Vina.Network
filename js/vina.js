// ============================================================================
// File: js/vina.js
// Description: Global JavaScript functions used across the Vina Network website.
// Created by: Vina Network
// ============================================================================

// BEGIN NAVBAR
// 1. Debounce function to limit the rate at which a function is triggered
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// 2. Change navbar style (e.g., background) when user scrolls down
document.addEventListener('scroll', debounce(() => {
    if (window.scrollY > 50) {
        document.querySelector('.navbar').classList.add('scrolled');
    } else {
        document.querySelector('.navbar').classList.remove('scrolled');
    }
}, 100));

// 3. Toggle burger menu (mobile view)
const burger = document.querySelector('.burger');
const navLinks = document.querySelector('.navbar-content');
const navbar = document.querySelector('.navbar');

function resetNavbarLayout() {
    if (navbar) {
        navbar.style.width = '100%';
        navbar.style.maxWidth = '100vw';
        console.log('Reset .navbar layout', {
            width: navbar.offsetWidth,
            viewportWidth: window.innerWidth
        });
    } else {
        console.error('Navbar not found');
    }
}

if (burger && navLinks) {
    burger.addEventListener('click', () => {
        burger.classList.toggle('active');
        navLinks.classList.toggle('active');
        document.body.style.overflow = navLinks.classList.contains('active') ? 'hidden' : '';
        resetNavbarLayout();
    });
} else {
    console.error('Burger or navLinks not found:', { burger, navLinks });
}

// 4. Handle menu link clicks
const navLinksItems = document.querySelectorAll('.navbar-link');
navLinksItems.forEach(link => {
    link.addEventListener('click', (e) => {
        if (window.innerWidth <= 768 && link.classList.contains('dropdown-toggle')) {
            e.preventDefault();
            const parentDropdown = link.closest('.dropdown');
            const dropdownMenu = parentDropdown.querySelector('.dropdown-menu');
            dropdownMenu.classList.toggle('active');
            link.classList.toggle('active');
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (menu !== dropdownMenu) menu.classList.remove('active');
            });
            document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                if (toggle !== link) toggle.classList.remove('active');
            });
            return;
        }
        if (navLinks && navLinks.classList.contains('active')) {
            burger.classList.remove('active');
            navLinks.classList.remove('active');
            document.body.style.overflow = '';
            resetNavbarLayout();
        }
    });
});

// 5. When clicking a dropdown item on mobile, close entire menu
document.querySelectorAll('.dropdown-link').forEach(item => {
    item.addEventListener('click', () => {
        if (window.innerWidth <= 768 && navLinks) {
            burger.classList.remove('active');
            navLinks.classList.remove('active');
            document.body.style.overflow = '';
            document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.remove('active'));
            document.querySelectorAll('.dropdown-toggle').forEach(toggle => toggle.classList.remove('active'));
            resetNavbarLayout();
        }
    });
});

// 6. Dropdown toggle behavior on desktop (click to open/close)
const dropdowns = document.querySelectorAll('.dropdown');
dropdowns.forEach(dropdown => {
    const toggle = dropdown.querySelector('.dropdown-toggle');
    const content = dropdown.querySelector('.dropdown-menu');
    
    if (window.innerWidth > 768) {
        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            content.classList.toggle('active');
            toggle.classList.toggle('active');
            dropdowns.forEach(d => {
                if (d !== dropdown) {
                    d.querySelector('.dropdown-menu').classList.remove('active');
                    d.querySelector('.dropdown-toggle').classList.remove('active');
                }
            });
        });
    }
});

// 7. Close dropdowns or mobile menu when clicking outside
document.addEventListener('click', (e) => {
    if (
        window.innerWidth <= 768 &&
        navLinks &&
        navLinks.classList.contains('active') &&
        !navLinks.contains(e.target) &&
        !burger.contains(e.target)
    ) {
        burger.classList.remove('active');
        navLinks.classList.remove('active');
        document.body.style.overflow = '';
        document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.remove('active'));
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => toggle.classList.remove('active'));
        resetNavbarLayout();
    }
    if (window.innerWidth > 768) {
        dropdowns.forEach(dropdown => {
            if (!dropdown.contains(e.target)) {
                dropdown.querySelector('.dropdown-menu').classList.remove('active');
                dropdown.querySelector('.dropdown-toggle').classList.remove('active');
            }
        });
    }
});

// 8. Listen for tools-content animation end to reset navbar
document.addEventListener('animationend', (e) => {
    if (e.target.classList.contains('tools-content') && e.animationName === 'slideIn') {
        resetNavbarLayout();
        console.log('Tools-content slideIn completed, navbar reset');
    }
});
// END NAVBAR

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
