// Debounce function to limit the frequency of function execution
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Activate fade-in for initial elements when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const fadeElements = document.querySelectorAll('.fade-in');
    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const delay = parseInt(entry.target.getAttribute('data-delay') || 0);
                setTimeout(() => {
                    entry.target.classList.add('visible');
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

    // Đảm bảo thẻ p có border nhấp nháy khi hiệu ứng typewriter hoàn tất (chỉ trên desktop)
    if (window.innerWidth > 768) {
        const heroP = document.querySelector('.hero-content p');
        heroP.style.borderRight = '2px solid #00d4ff';
        setTimeout(() => {
            heroP.style.borderRight = 'none'; // Tắt border sau khi hiệu ứng hoàn tất
        }, 4000); // Thời gian khớp với animation typewriter (4s)
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
        // If it is a dropdown-toggle, do not close the burger menu immediately
        if (!link.classList.contains('dropdown-toggle')) {
            navLinksItems.forEach(item => item.classList.remove('active'));
            link.classList.add('active');
            // Close burger menu when clicking a non-dropdown link
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
        e.preventDefault(); // Prevent default link behavior
        // Toggle submenu
        content.classList.toggle('active');
        // Ensure other submenus are closed
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
