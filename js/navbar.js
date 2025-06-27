// ============================================================================
// File: js/navbar.js
// Description: JavaScript logic for handling the behavior of the navigation bar.
// Created by: Vina Network
// ============================================================================

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

burger.addEventListener('click', () => {
    burger.classList.toggle('active');
    navLinks.classList.toggle('active');
    // Disable body scroll when mobile menu is open
    document.body.style.overflow = navLinks.classList.contains('active') ? 'hidden' : '';
});

// 4. Handle menu link clicks
const navLinksItems = document.querySelectorAll('.nav-link');
navLinksItems.forEach(link => {
    link.addEventListener('click', (e) => {
        // Handle dropdown toggle on mobile
        if (window.innerWidth <= 768 && link.classList.contains('dropdown-toggle')) {
            e.preventDefault();
            const parentDropdown = link.closest('.dropdown');
            const dropdownMenu = parentDropdown.querySelector('.dropdown-menu');
            dropdownMenu.classList.toggle('active');
            link.classList.toggle('active');

            // Close other open dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (menu !== dropdownMenu) menu.classList.remove('active');
            });
            document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                if (toggle !== link) toggle.classList.remove('active');
            });
            return;
        }

        // For regular links: close burger menu if open
        if (navLinks.classList.contains('active')) {
            burger.classList.remove('active');
            navLinks.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

// 5. When clicking a dropdown item on mobile, close entire menu
document.querySelectorAll('.dropdown-link').forEach(item => {
    item.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            burger.classList.remove('active');
            navLinks.classList.remove('active');
            document.body.style.overflow = '';
            document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.remove('active'));
            document.querySelectorAll('.dropdown-toggle').forEach(toggle => toggle.classList.remove('active'));
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

            // Close other dropdowns
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
    // Close mobile menu if user clicks outside
    if (
        window.innerWidth <= 768 &&
        navLinks.classList.contains('active') &&
        !navLinks.contains(e.target) &&
        !burger.contains(e.target)
    ) {
        burger.classList.remove('active');
        navLinks.classList.remove('active');
        document.body.style.overflow = '';
        document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.remove('active'));
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => toggle.classList.remove('active'));
    }

    // Close desktop dropdowns if user clicks outside
    if (window.innerWidth > 768) {
        dropdowns.forEach(dropdown => {
            if (!dropdown.contains(e.target)) {
                dropdown.querySelector('.dropdown-menu').classList.remove('active');
                dropdown.querySelector('.dropdown-toggle').classList.remove('active');
            }
        });
    }
});
