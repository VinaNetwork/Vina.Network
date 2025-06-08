// 3. Đổi style Navbar khi cuộn trang với debounce
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}
document.addEventListener('scroll', debounce(() => {
    if (window.scrollY > 50) {
        document.querySelector('.navbar').classList.add('scrolled');
    } else {
        document.querySelector('.navbar').classList.remove('scrolled');
    }
}, 100));

// 4. Hiệu ứng menu mobile (Burger Menu)
const burger = document.querySelector('.burger');
const navLinks = document.querySelector('.nav-1');
burger.addEventListener('click', () => {
    burger.classList.toggle('active');
    navLinks.classList.toggle('active');
    document.body.style.overflow = navLinks.classList.contains('active') ? 'hidden' : '';
});

// 5. Xử lý trạng thái active cho các menu links
const navLinksItems = document.querySelectorAll('.nav-link');
navLinksItems.forEach(link => {
    link.addEventListener('click', (e) => {
        // Nếu là dropdown-toggle trên mobile, chỉ xổ menu con, không đóng menu chính
        if (window.innerWidth <= 768 && link.classList.contains('dropdown-toggle')) {
            e.preventDefault();
            const parentDropdown = link.closest('.dropdown');
            const dropdownMenu = parentDropdown.querySelector('.dropdown-menu');
            dropdownMenu.classList.toggle('active');
            link.classList.toggle('active');
            // Đóng các dropdown khác
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (menu !== dropdownMenu) menu.classList.remove('active');
            });
            document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                if (toggle !== link) toggle.classList.remove('active');
            });
            return;
        }
        // Link thường: đóng menu mobile
        if(navLinks.classList.contains('active')) {
            burger.classList.remove('active');
            navLinks.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

// Khi click vào dropdown-item trên mobile, đóng menu luôn
document.querySelectorAll('.dropdown-item').forEach(item => {
    item.addEventListener('click', () => {
        if(window.innerWidth <= 768) {
            burger.classList.remove('active');
            navLinks.classList.remove('active');
            document.body.style.overflow = '';
            document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.remove('active'));
            document.querySelectorAll('.dropdown-toggle').forEach(toggle => toggle.classList.remove('active'));
        }
    });
});

// 6. Dropdown menu (sổ menu con) trên desktop
const dropdowns = document.querySelectorAll('.dropdown');
dropdowns.forEach(dropdown => {
    const toggle = dropdown.querySelector('.dropdown-toggle');
    const content = dropdown.querySelector('.dropdown-menu');
    // Chỉ xử lý hover trên desktop
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

// 7. Đóng dropdown khi click ra ngoài (mobile + desktop)
document.addEventListener('click', (e) => {
    // Đóng toàn bộ navbar mobile khi bấm ngoài
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

    // Đóng dropdown menu nếu click ra ngoài vùng dropdown (desktop)
    if (window.innerWidth > 768) {
        dropdowns.forEach(dropdown => {
            if (!dropdown.contains(e.target)) {
                dropdown.querySelector('.dropdown-menu').classList.remove('active');
                dropdown.querySelector('.dropdown-toggle').classList.remove('active');
            }
        });
    }
});
