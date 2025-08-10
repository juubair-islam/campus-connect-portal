// main.js

function checkLogin() {
  const user = sessionStorage.getItem('user');
  if (!user) {
    window.location.href = 'index.html';
  }
}

function showMessage(element, message, success = false) {
  element.textContent = message;
  element.style.color = success ? 'green' : 'red';
  if (message) {
    setTimeout(() => {
      element.textContent = '';
    }, 3000);
  }
}
