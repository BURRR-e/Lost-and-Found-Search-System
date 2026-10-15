const menu = document.getElementById('navMenu');
const navLinks = document.querySelector('.nav-links');

menu.addEventListener('click', function() {
    navLinks.classList.toggle('active');
});

const loginBtn = document.getElementById("loginBtn");
const signupBtn = document.getElementById("signupBtn");

loginBtn.addEventListener("click", function () {
    window.location.href = "/page/signin.html";
});

signupBtn.addEventListener("click", function () {
    window.location.href = "/page/signup.html";
});