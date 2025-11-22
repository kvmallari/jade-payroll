/**
 * Reusable Context Menu Script for Settings Tables
 * This script provides right-click context menu functionality for all settings pages
 */

class SettingsContextMenu {
    constructor() {
        this.currentItemId = null;
        this.contextMenu = null;
        this.init();
    }

    init() {
        // Create context menu if it doesn't exist
        if (!document.getElementById("contextMenu")) {
            this.createContextMenu();
        }
        this.contextMenu = document.getElementById("contextMenu");
        this.bindEvents();
    }

    createContextMenu() {
        const contextMenuHTML = `
            <div id="contextMenu" class="fixed bg-white rounded-md shadow-xl border border-gray-200 py-1 z-50 hidden min-w-48 backdrop-blur-sm transition-all duration-150 transform opacity-0 scale-95">
                <div id="contextMenuHeader" class="px-3 py-2 border-b border-gray-100 bg-gray-50 rounded-t-md">
                    <div class="text-sm font-medium text-gray-900" id="contextMenuName"></div>
                    <div class="text-xs text-gray-500" id="contextMenuSubtitle"></div>
                </div>
                <div class="py-1">
                    <a href="#" id="contextMenuView" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
                        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        <span id="viewText">View Details</span>
                    </a>
                    <a href="#" id="contextMenuEdit" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
                        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        <span id="editText">Edit</span>
                    </a>
                    <div class="border-t border-gray-100 my-1"></div>
                    <a href="#" id="contextMenuToggle" class="flex items-center px-3 py-2 text-sm text-yellow-700 hover:bg-yellow-50 hover:text-yellow-800 transition-colors duration-150">
                        <svg class="w-4 h-4 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path>
                        </svg>
                        <span id="toggleText">Toggle Status</span>
                    </a>
                    <a href="#" id="contextMenuDelete" class="flex items-center px-3 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors duration-150">
                        <svg class="w-4 h-4 mr-2 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        <span id="deleteText">Delete</span>
                    </a>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML("beforeend", contextMenuHTML);
    }

    bindEvents() {
        // Hide context menu when clicking elsewhere
        document.addEventListener("click", () => this.hide());
        document.addEventListener("contextmenu", (e) => {
            if (!e.target.closest("[data-context-menu]")) {
                this.hide();
            }
        });
    }

    show(event, config) {
        event.preventDefault();
        event.stopPropagation();

        this.currentItemId = config.id;

        // Update context menu content
        document.getElementById("contextMenuName").textContent = config.name;
        document.getElementById("contextMenuSubtitle").textContent =
            config.subtitle || "";

        // Update action texts
        if (config.viewText)
            document.getElementById("viewText").textContent = config.viewText;
        if (config.editText)
            document.getElementById("editText").textContent = config.editText;
        if (config.deleteText)
            document.getElementById("deleteText").textContent =
                config.deleteText;

        // Update links
        document.getElementById("contextMenuView").href = config.viewUrl || "#";
        document.getElementById("contextMenuEdit").href = config.editUrl || "#";

        // Update toggle button text
        const toggleText = document.getElementById("toggleText");
        toggleText.textContent = config.isActive ? "Deactivate" : "Activate";

        // Show/hide delete option
        const deleteOption = document.getElementById("contextMenuDelete");
        deleteOption.style.display =
            config.canDelete !== false ? "flex" : "none";

        // Store URLs for form actions
        this.toggleUrl = config.toggleUrl;
        this.deleteUrl = config.deleteUrl;
        this.deleteConfirmMessage =
            config.deleteConfirmMessage ||
            "Are you sure you want to delete this item?";

        // Position and show context menu
        this.showAtPosition(event);
    }

    showAtPosition(event) {
        const mouseX = event.clientX;
        const mouseY = event.clientY;

        this.contextMenu.style.left = mouseX + "px";
        this.contextMenu.style.top = mouseY + "px";
        this.contextMenu.classList.remove("hidden");

        setTimeout(() => {
            this.contextMenu.classList.remove("opacity-0", "scale-95");
            this.contextMenu.classList.add("opacity-100", "scale-100");
        }, 10);

        // Adjust position to prevent menu from going off-screen
        setTimeout(() => {
            const menuRect = this.contextMenu.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;

            let adjustedX = mouseX;
            let adjustedY = mouseY;

            if (mouseX + menuRect.width > viewportWidth) {
                adjustedX = mouseX - menuRect.width;
            }

            if (mouseY + menuRect.height > viewportHeight) {
                adjustedY = mouseY - menuRect.height;
            }

            adjustedX = Math.max(10, adjustedX);
            adjustedY = Math.max(10, adjustedY);

            this.contextMenu.style.left = adjustedX + "px";
            this.contextMenu.style.top = adjustedY + "px";
        }, 20);
    }

    hide() {
        if (!this.contextMenu) return;

        this.contextMenu.classList.add("opacity-0", "scale-95");
        this.contextMenu.classList.remove("opacity-100", "scale-100");
        setTimeout(() => {
            this.contextMenu.classList.add("hidden");
        }, 150);
    }

    // Helper method to submit forms
    submitForm(action, method = "POST", additionalFields = {}) {
        const form = document.createElement("form");
        form.method = "POST";
        form.action = action;

        // CSRF token
        const csrfToken = document.createElement("input");
        csrfToken.type = "hidden";
        csrfToken.name = "_token";
        csrfToken.value =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content") || "";
        form.appendChild(csrfToken);

        // Method override if needed
        if (method !== "POST") {
            const methodField = document.createElement("input");
            methodField.type = "hidden";
            methodField.name = "_method";
            methodField.value = method;
            form.appendChild(methodField);
        }

        // Additional fields
        Object.entries(additionalFields).forEach(([name, value]) => {
            const field = document.createElement("input");
            field.type = "hidden";
            field.name = name;
            field.value = value;
            form.appendChild(field);
        });

        document.body.appendChild(form);
        form.submit();
    }

    // Set up toggle functionality
    setupToggle() {
        document
            .getElementById("contextMenuToggle")
            .addEventListener("click", (e) => {
                e.preventDefault();
                if (this.currentItemId && this.toggleUrl) {
                    this.submitForm(this.toggleUrl, "PATCH");
                }
                this.hide();
            });
    }

    // Set up delete functionality
    setupDelete() {
        document
            .getElementById("contextMenuDelete")
            .addEventListener("click", (e) => {
                e.preventDefault();
                if (
                    this.currentItemId &&
                    this.deleteUrl &&
                    confirm(this.deleteConfirmMessage)
                ) {
                    this.submitForm(this.deleteUrl, "DELETE");
                }
                this.hide();
            });
    }
}

// Initialize the context menu system
window.settingsContextMenu = new SettingsContextMenu();

// Set up toggle and delete handlers after DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
    window.settingsContextMenu.setupToggle();
    window.settingsContextMenu.setupDelete();
});

// Global function for easy use in templates
window.showSettingsContextMenu = function (event, config) {
    window.settingsContextMenu.show(event, config);
};
