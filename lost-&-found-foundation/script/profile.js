import { isValidEmail, isNotEmpty } from './validation.js';

document.addEventListener('DOMContentLoaded', () => {
    const editForm = document.querySelector('#edit-profile form');
    const nameInput = document.getElementById('full-name');
    const emailInput = document.getElementById('account-email');

    const savedUser = JSON.parse(localStorage.getItem('userData')) || {
        fullName: 'Sample User',
        email: 'user@school.edu'
    };

    if (nameInput && emailInput) {
        nameInput.value = savedUser.fullName;
        emailInput.value = savedUser.email;
    }

    if (editForm) {
        editForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const nameVal = nameInput.value.trim();
            const emailVal = emailInput.value.trim();

            if (!isNotEmpty(nameVal)) {
                alert('Full name cannot be empty.');
                return;
            }

            if (!isValidEmail(emailVal)) {
                alert('Please enter a valid email address.');
                return;
            }

            const updatedUser = { ...savedUser, fullName: nameVal, email: emailVal };
            localStorage.setItem('userData', JSON.stringify(updatedUser));
            alert('Profile updated successfully!');
        });
    }
});