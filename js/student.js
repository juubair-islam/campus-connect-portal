document.addEventListener("DOMContentLoaded", async () => {
  const welcomeMessage = document.getElementById("welcomeMessage");
  const studentNameDisplay = document.getElementById("studentNameDisplay");
  const studentMeta = document.getElementById("studentMeta");
  const studentNameSmall = document.getElementById("studentNameSmall");
  const currentDate = document.getElementById("currentDate");
  const logoutBtn = document.getElementById("logoutBtn");

  // Set date
  const today = new Date();
  currentDate.textContent = `${today.getMonth() + 1}/${today.getDate()}/${today.getFullYear()}`;

  try {
    const res = await fetch("php/student.php");
    const data = await res.json();

    if (!data.success) {
      alert(data.error || "Not logged in. Redirecting to login.");
      location.href = "login.php";
      return;
    }

    const { name, iub_id, department, major, minor } = data;

    // Set content
    welcomeMessage.textContent = `Welcome, ${name}!`;
    studentNameDisplay.textContent = name;
    studentNameSmall.textContent = name;

    studentMeta.textContent = `IUB ID: ${iub_id} | Department: ${department} | Major: ${major || 'N/A'} | Minor: ${minor || 'None'}`;
  } catch (err) {
    alert("Something went wrong while loading your dashboard.");
  }

  logoutBtn.addEventListener("click", async () => {
    await fetch("php/logout.php");
    location.href = "login.php";
  });
});
