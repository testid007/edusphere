// Admin schedule controls (works even when manage-schedule.php is loaded via include or AJAX)
(function () {
  console.log("[admin-schedule] script loaded");

  function setStatus(text, type) {
    const statusEl = document.querySelector("#status");
    if (!statusEl) return;
    statusEl.className = "small text-" + (type || "muted");
    statusEl.textContent = text;
  }

  function getGrade() {
    const gradeSelect = document.querySelector("#grade-select");
    return gradeSelect ? gradeSelect.value : "";
  }

  function loadSchedule() {
    const grade = getGrade();
    const displayEl = document.querySelector("#schedule-display");

    console.log("[admin-schedule] loadSchedule, grade =", grade);

    if (!grade) {
      setStatus("Please select a class first.", "danger");
      if (displayEl) {
        displayEl.innerHTML =
          '<div class="alert alert-info">Select a class/grade.</div>';
      }
      return;
    }

    setStatus("Loading schedule...", "muted");

    // From /edusphere/dashboards/admin/dashboard.php → ../../api/... = /edusphere/api/...
    fetch(`../../api/fetch_schedule.php?grade=${encodeURIComponent(grade)}`, {
      cache: "no-cache",
    })
      .then((r) => r.text())
      .then((html) => {
        console.log("[admin-schedule] schedule loaded, length =", html.length);
        if (displayEl) displayEl.innerHTML = html;
        setStatus("Schedule loaded.", "success");
      })
      .catch((err) => {
        console.error("[admin-schedule] error loading schedule", err);
        if (displayEl) {
          displayEl.innerHTML =
            '<div class="alert alert-danger">Error loading schedule.</div>';
        }
        setStatus("Failed to load schedule.", "danger");
      });
  }

  function autoGenerate() {
    const grade = getGrade();
    console.log("[admin-schedule] autoGenerate, grade =", grade);

    if (!grade) {
      setStatus("Please select a class first.", "danger");
      return;
    }

    const btnAuto = document.querySelector("#btn-auto-generate");
    setStatus("Generating schedule...", "muted");
    if (btnAuto) btnAuto.disabled = true;

    fetch("../../api/auto_generate_schedule.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "grade=" + encodeURIComponent(grade),
    })
      .then((r) => {
        console.log("[admin-schedule] auto-generate HTTP status", r.status);
        return r.text();
      })
      .then((text) => {
        let data;
        try {
          data = JSON.parse(text);
        } catch (e) {
          console.error("[admin-schedule] JSON parse error, raw =", text);
          setStatus("Server returned invalid JSON.", "danger");
          return;
        }

        console.log("[admin-schedule] auto-generate data", data);
        if (data.success) {
          setStatus(
            data.message || "Schedule generated successfully!",
            "success"
          );
          loadSchedule();
        } else {
          setStatus(data.message || "Failed to generate schedule.", "danger");
        }
      })
      .catch((err) => {
        console.error("[admin-schedule] error during auto-generate", err);
        setStatus("Error while generating schedule.", "danger");
      })
      .finally(() => {
        if (btnAuto) btnAuto.disabled = false;
      });
  }

  // Event delegation – works even if manage-schedule.php is injected later
  document.addEventListener("click", function (e) {
    if (e.target.matches("#btn-load")) {
      e.preventDefault();
      loadSchedule();
    } else if (e.target.matches("#btn-auto-generate")) {
      e.preventDefault();
      autoGenerate();
    }
  });

  document.addEventListener("change", function (e) {
    if (e.target.matches("#grade-select")) {
      loadSchedule();
    }
  });
  document.addEventListener("click", function (e) {
    if (e.target.matches("#btn-delete-schedule")) {
      const grade = document.querySelector("#grade-select").value;
      if (!grade) return alert("Select class first!");

      fetch("../../api/delete_schedule.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "grade=" + encodeURIComponent(grade),
      })
        .then((r) => r.json())
        .then((d) => {
          alert(d.message);
          if (d.success) {
            document.querySelector("#schedule-display .card-body").innerHTML =
              '<div class="alert alert-info">Schedule deleted. You can generate new one.</div>';
          }
        });
    }

    if (e.target.matches("#btn-edit-schedule")) {
      const grade = document.querySelector("#grade-select").value;
      if (!grade) return alert("Select class first!");
      window.location.href =
        "../admin/manual-schedule-edit.php?class=" + encodeURIComponent(grade);
    }
  });
})();
