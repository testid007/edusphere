document.addEventListener("DOMContentLoaded", function () {
  const roleButtons = document.querySelectorAll(".role-toggle button");
  const hiddenRoleInput = document.querySelector('input[name="role"]');
  const classRow = document.getElementById("class-row");

  // Function to show or hide the class dropdown based on role
  function updateClassVisibility(role) {
    if (role === "Student") {
      classRow.style.display = "flex";
      classRow.querySelector("select").required = true;
    } else {
      classRow.style.display = "none";
      classRow.querySelector("select").required = false;
    }
  }

  // Add click listeners to all role buttons
  roleButtons.forEach((button) => {
    button.addEventListener("click", () => {
      // Remove active class from all buttons
      roleButtons.forEach((btn) => btn.classList.remove("active"));

      // Add active class to the clicked button
      button.classList.add("active");

      // Update hidden input with selected role
      const selectedRole = button.textContent.trim();
      hiddenRoleInput.value = selectedRole;

      // Show/hide class dropdown accordingly
      updateClassVisibility(selectedRole);
    });
  });

  // On page load, set visibility of class dropdown correctly
  updateClassVisibility(hiddenRoleInput.value);
});
