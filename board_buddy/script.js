// ===== Load Notifications =====
async function loadNotifications() {
  try {
    const response = await fetch(`notifications/fetch.php?user_type=${userType}`);
    const data = await response.json();

    const container = document.querySelector(".notifications");
    container.innerHTML = ""; // clear old

    data.forEach(n => {
      const div = document.createElement("div");
      div.classList.add("notification");
      div.setAttribute("data-id", n.id);

      div.innerHTML = `
        <div class="notification-left">
          <span class="icon">📩</span>
          <div class="text">
            <p><strong>${n.title}:</strong> ${n.message}</p>
            <small>${n.created_at}</small>
          </div>
        </div>
        <div class="actions">
          <span class="star ${n.is_important == 1 ? "active" : ""}">
            ${n.is_important == 1 ? "★" : "☆"}
          </span>
          <span class="menu">⋮</span>
        </div>
      `;

      container.appendChild(div);
    });

    attachEvents(); // reattach events after rendering
  } catch (err) {
    console.error("Error loading notifications:", err);
  }
}


function attachEvents() {
  // ⭐ Star toggle (update DB)
  document.querySelectorAll(".star").forEach(star => {
    star.addEventListener("click", async () => {
      const notification = star.closest(".notification");
      const id = notification.dataset.id;
      const isImportant = star.classList.contains("active") ? 0 : 1;

      // Update UI immediately
      if (isImportant) {
        star.classList.add("active");
        star.textContent = "★";
      } else {
        star.classList.remove("active");
        star.textContent = "☆";
      }

      // Update DB
      await fetch("notifications/update_star.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id, is_important: isImportant })
      });
    });
  });

  // ⋮ Menu with delete option
  document.querySelectorAll(".menu").forEach(menu => {
    menu.addEventListener("click", (e) => {
      e.stopPropagation();
      document.querySelectorAll(".dropdown").forEach(d => d.remove());

      const dropdown = document.createElement("div");
      dropdown.classList.add("dropdown");

      const deleteBtn = document.createElement("button");
      deleteBtn.textContent = "Delete";
      deleteBtn.addEventListener("click", () => {
        const id = menu.closest(".notification").dataset.id;
        document.getElementById("deleteModal").style.display = "flex";
        window.targetDeleteId = id;
      });

      dropdown.appendChild(deleteBtn);
      menu.parentElement.appendChild(dropdown);
      dropdown.style.display = "flex";
    });
  });
}

// ===== Modal Buttons =====
const modal = document.getElementById("deleteModal");

document.getElementById("confirmDelete").addEventListener("click", async () => {
  if (window.targetDeleteId) {
    await fetch("notifications/delete.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: window.targetDeleteId })
    });
    loadNotifications(); // reload UI
    window.targetDeleteId = null;
  }
  modal.style.display = "none";
});

document.getElementById("cancelDelete").addEventListener("click", () => {
  modal.style.display = "none";
  window.targetDeleteId = null;
});

window.addEventListener("click", (e) => {
  if (e.target === modal) {
    modal.style.display = "none";
    window.targetDeleteId = null;
  }
});


loadNotifications();
