const questions = document.getElementById("questions");

questions.addEventListener("submit", function(event) {
  event.preventDefault();

  const name = document.getElementById("name").value.trim();
  const email = document.getElementById("email").value.trim().toLowerCase();
  const schoolid = document.getElementById("schoolid").value.trim();
  const role = document.getElementById("role").value;
  const password = document.getElementById("password").value;

  const accounts = JSON.parse(localStorage.getItem("accounts")) || [];

  const existingAccount = accounts.find(function(account) {
    return account.email.toLowerCase() === email ||
           account.schoolid === schoolid;
  });

  if (existingAccount) {
    alert("This email or School ID is already registered.");
    return;
  }

  const newAccount = {
    name: name,
    email: email,
    schoolid: schoolid,
    role: role,
    password: password
  };

  accounts.push(newAccount);

  localStorage.setItem("accounts", JSON.stringify(accounts));

  alert("Account created successfully!");

  window.location.href = "signin.html";
});