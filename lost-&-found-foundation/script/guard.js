// Page guard. Usage in <head>:
//   <script src="/script/guard.js" data-role="user"></script>     (any signed-in user)
//   <script src="/script/guard.js" data-role="admin" data-signout="nav"></script>
// This only hides pages from the wrong visitor; the API enforces access on the server.
(function () {
  const tag = document.currentScript;
  const need = (tag && tag.dataset.role) || 'user';
  const addSignOut = tag && tag.dataset.signout === 'nav';
  const signinUrl = need === 'admin' ? '/page/admin-signin.html' : '/page/signin.html';

  document.documentElement.style.visibility = 'hidden';

  function redirect(url) {
    window.location.replace(url);
  }

  fetch('/api/auth.php?action=me', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (json) {
      const user = json && json.data;
      if (!user) {
        redirect(signinUrl + '?next=' + encodeURIComponent(location.pathname));
        return;
      }
      if (need === 'admin' && user.role !== 'Admin') {
        redirect('/page/home.html');
        return;
      }
      window.currentUser = user;
      document.documentElement.style.visibility = '';

      if (addSignOut) {
        document.addEventListener('DOMContentLoaded', function () {
          const links = document.querySelector('.nav-links');
          if (!links) return;
          const a = document.createElement('a');
          a.href = '#';
          a.innerHTML = '<i class="fa-solid fa-right-from-bracket"></i><span>Sign out</span>';
          a.addEventListener('click', function (e) {
            e.preventDefault();
            fetch('/api/auth.php?action=logout', { method: 'POST', credentials: 'same-origin' })
              .finally(function () { redirect(signinUrl); });
          });
          links.appendChild(a);
        });
      }
    })
    .catch(function () { redirect(signinUrl); });
})();
