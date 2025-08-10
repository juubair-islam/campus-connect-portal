// dashboard.js

document.addEventListener('DOMContentLoaded', () => {
  const user = JSON.parse(sessionStorage.getItem('user'));
  if (!user) {
    window.location.href = 'index.html';
    return;
  }

  const userNameDisplay = document.getElementById('userNameDisplay');
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');

  userNameDisplay.textContent = user.name || user.email || 'User';

  // Render sidebar based on role
  let links = [];

  if (user.role === 'admin' || user.role === 'faculty') {
    links = [
      { href: '#overview', text: 'Overview' },
      { href: '#lostfound', text: 'Lost & Found' },
      { href: '#cctv', text: 'CCTV Reports' },
      { href: '#banner', text: 'Banner Requisition' },
      { href: '#eventbooking', text: 'Event Booking' },
      { href: '#idcards', text: 'ID Cards' },
      { href: '#studentlookup', text: 'Student Lookup' },
      { href: '#pettycash', text: 'Petty Cash' }
    ];
  } else if (user.role === 'student' || user.role === 'alumni') {
    links = [
      { href: '#lostfound', text: 'Lost & Found' },
      { href: '#tutorpanel', text: 'Tutor Panel' },
      { href: '#learnerpanel', text: 'Learner Panel' },
    ];
  }

  sidebar.innerHTML = links.map(link => `<a href="${link.href}" class="sidebar-link">${link.text}</a>`).join('');

  // Handle sidebar clicks
  sidebar.addEventListener('click', (e) => {
    if (e.target.tagName === 'A') {
      e.preventDefault();
      Array.from(sidebar.querySelectorAll('a')).forEach(a => a.classList.remove('active'));
      e.target.classList.add('active');
      loadModule(e.target.getAttribute('href'));
    }
  });

  // Load default module
  loadModule(links.length > 0 ? links[0].href : '');

  function loadModule(hash) {
    mainContent.innerHTML = '';

    switch (hash) {
      case '#overview':
        mainContent.innerHTML = '<h2>Overview</h2><p>Admin overview and stats here.</p>';
        break;
      case '#lostfound':
        import('./modules/lostfound.js').then(module => module.renderLostFound(mainContent, user));
        break;
      case '#cctv':
        mainContent.innerHTML = '<h2>CCTV Reports</h2><p>Coming soon...</p>';
        break;
      case '#banner':
        mainContent.innerHTML = '<h2>Banner Requisition</h2><p>Coming soon...</p>';
        break;
      case '#eventbooking':
        mainContent.innerHTML = '<h2>Event Booking</h2><p>Coming soon...</p>';
        break;
      case '#idcards':
        mainContent.innerHTML = '<h2>ID Cards</h2><p>Coming soon...</p>';
        break;
      case '#studentlookup':
        mainContent.innerHTML = '<h2>Student Lookup</h2><p>Coming soon...</p>';
        break;
      case '#pettycash':
        mainContent.innerHTML = '<h2>Petty Cash</h2><p>Coming soon...</p>';
        break;
      case '#tutorpanel':
        import('./modules/tutor.js').then(module => module.renderTutorPanel(mainContent, user));
        break;
      case '#learnerpanel':
        import('./modules/learner.js').then(module => module.renderLearnerPanel(mainContent, user));
        break;
      default:
        mainContent.innerHTML = '<h2>Welcome</h2><p>Select a module from the sidebar.</p>';
    }
  }

  document.getElementById('logoutBtn').addEventListener('click', () => {
    sessionStorage.clear();
    window.location.href = 'index.html';
  });
});
