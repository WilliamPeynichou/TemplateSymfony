import './styles/app.css';
import './styles/home.css';

// Nav — effet au scroll
const nav = document.getElementById('tactical-nav');
window.addEventListener('scroll', () => {
    nav.classList.toggle('tactical-scrolled', window.scrollY > 50);
}, { passive: true });

// Reveal au scroll — IntersectionObserver
const reveals = document.querySelectorAll('.reveal');
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.15 });

reveals.forEach(el => observer.observe(el));
