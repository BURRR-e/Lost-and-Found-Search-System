const questions = document.getElementById("questions");

questions.addEventListener("submit", function(event) {
  event.preventDefault();

  const email = document.getElementById("email").value.trim();
  const password = document.getElementById("password").value;

  const accounts = JSON.parse(localStorage.getItem("accounts")) || [];

  const account = accounts.find(function(user) {
    return user.email === email && user.password === password;
  });

  if (!account) {
    alert("Invalid email or password.");
    return;
  }

  localStorage.setItem("currentUser", JSON.stringify(account));

  if (account.role === "admin") {
    window.location.href = "/admin/adminAccount.html";
  } else {
    window.location.href = "/page/home.html";
  }
});