const menu = document.getElementById('navMenu');
const navLinks = document.querySelector('.nav-links');

menu.addEventListener('click', function() {
    navLinks.classList.toggle('active');
});