/**
 * TeacherMatch - Main JavaScript file
 */

document.addEventListener("DOMContentLoaded", () => {
    // Toggle mobile menu
    const mobileMenuToggle = document.getElementById("mobile-menu-toggle")
    const sidebar = document.querySelector(".sidebar")
  
    if (mobileMenuToggle && sidebar) {
      mobileMenuToggle.addEventListener("click", () => {
        sidebar.classList.toggle("active")
      })
    }
  
    // Notifications dropdown
    const notificationToggle = document.getElementById("notifications-toggle")
    const notificationsDropdown = document.getElementById("notifications-dropdown")
  
    if (notificationToggle && notificationsDropdown) {
      notificationToggle.addEventListener("click", (e) => {
        e.stopPropagation()
        notificationsDropdown.classList.toggle("hidden")
      })
  
      document.addEventListener("click", (e) => {
        if (!notificationsDropdown.contains(e.target) && e.target !== notificationToggle) {
          notificationsDropdown.classList.add("hidden")
        }
      })
    }
  
    // Form validations
    const forms = document.querySelectorAll("form")
  
    forms.forEach((form) => {
      form.addEventListener("submit", (e) => {
        let isValid = true
        const requiredFields = form.querySelectorAll("[required]")
  
        requiredFields.forEach((field) => {
          if (!field.value.trim()) {
            isValid = false
            const errorElement = document.createElement("div")
            errorElement.className = "error-message"
            errorElement.textContent = "This field is required"
  
            // Remove existing error messages
            const existingError = field.parentNode.querySelector(".error-message")
            if (existingError) {
              existingError.remove()
            }
  
            field.parentNode.appendChild(errorElement)
          }
        })
  
        if (!isValid) {
          e.preventDefault()
        }
      })
    })
  
    // Search filter tags
    const filterTags = document.querySelectorAll(".filter-tag")
  
    filterTags.forEach((tag) => {
      tag.addEventListener("click", () => {
        tag.classList.toggle("active")
        // In a real app, you would update the search results based on selected filters
      })
    })
  
    // Initialize tabs
    const tabTriggers = document.querySelectorAll(".tab-trigger")
  
    tabTriggers.forEach((trigger) => {
      trigger.addEventListener("click", function (e) {
        if (!trigger.getAttribute("href").includes("?")) {
          e.preventDefault()
  
          const tabId = this.getAttribute("data-tab")
          const tabContent = document.getElementById(tabId)
  
          // Hide all tab contents
          document.querySelectorAll(".tab-content").forEach((content) => {
            content.classList.remove("active")
          })
  
          // Deactivate all triggers
          tabTriggers.forEach((t) => {
            t.classList.remove("active")
          })
  
          // Activate clicked tab
          tabContent.classList.add("active")
          this.classList.add("active")
        }
      })
    })
  })
  
  // Handle request buttons
  function acceptRequest(requestId) {
    // In a real app, you would make an AJAX call to update the request status
    console.log("Accept request:", requestId)
    alert("Request accepted!")
  }
  
  function declineRequest(requestId) {
    // In a real app, you would make an AJAX call to update the request status
    console.log("Decline request:", requestId)
    alert("Request declined!")
  }
  
  function sendMessage(userId) {
    // In a real app, you would open a messaging modal or redirect to messages page
    console.log("Send message to:", userId)
    alert("Message feature would open here")
  }
  
  function viewProfile(userId) {
    // In a real app, you would redirect to the user's profile
    window.location.href = "teacher-profile.php?id=" + userId
  }
  
  // Logout function
  function logout() {
    // In a real app, you would make a call to destroy the session
    window.location.href = "logout.php"
  }
  