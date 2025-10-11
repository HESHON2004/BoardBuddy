const uploadPic = document.getElementById('uploadPic');
const profilePic = document.getElementById('profilePic');
const uploadBtn = document.getElementById('uploadBtn');
const form = document.getElementById('profileForm');
let editBtn = document.getElementById('editBtn');
const actionButtons = document.getElementById('actionButtons');

// Show image preview immediately
uploadPic.addEventListener('change', function () {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = e => profilePic.src = e.target.result;
        reader.readAsDataURL(file);
    }
});

// Enable edit mode
function enableEditMode() {
    const inputs = form.querySelectorAll('input[name]');
    inputs.forEach(input => input.disabled = false);

    // Show upload button
    if (uploadBtn) uploadBtn.style.display = 'inline-block';

    // Replace action buttons
    actionButtons.innerHTML = `
        <button class="btn btn-cancel" id="cancelBtn">Cancel</button>
        <button class="btn btn-save" id="saveBtn">Save Changes</button>
    `;

    // Attach event listeners for the new buttons
    attachCancelSave();
}

// Attach event listeners for Cancel and Save buttons
function attachCancelSave() {
    const cancelBtn = document.getElementById('cancelBtn');
    const saveBtn = document.getElementById('saveBtn');
    const inputs = form.querySelectorAll('input[name]');

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function (e) {
            e.preventDefault();
            inputs.forEach(input => input.disabled = true);
            if (uploadBtn) uploadBtn.style.display = 'none';
            resetEditButton();
        });
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', function (e) {
            e.preventDefault();
            form.submit(); // Submit the form
        });
    }
}

// Reset action buttons to the original Edit button
function resetEditButton() {
    actionButtons.innerHTML = `<button class="btn btn-edit" id="editBtn">Edit Profile</button>`;
    editBtn = document.getElementById('editBtn');

    // Reattach the edit button listener
    editBtn.addEventListener('click', function (e) {
        e.preventDefault();
        enableEditMode();
    });

    // Disable inputs again
    const inputs = form.querySelectorAll('input[name]');
    inputs.forEach(input => input.disabled = true);
}

// Initial edit button listener
if (editBtn) {
    editBtn.addEventListener('click', function (e) {
        e.preventDefault();
        enableEditMode();
    });
}
