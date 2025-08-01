// Indie500 Manager Admin JavaScript
const $ = window.jQuery // Declare the jQuery variable

$(document).ready(($) => {
  // File upload validation
  $("#csv_file").on("change", function () {
    const file = this.files[0]
    if (file) {
      const fileName = file.name.toLowerCase()
      if (!fileName.endsWith(".csv")) {
        alert("Please select a valid CSV file.")
        $(this).val("")
      }
    }
  })

  // Confirm before large operations
  $("form").on("submit", function (e) {
    if ($(this).find('input[name="upload_csv"]').length) {
      if (!confirm("This will replace all existing songs. Are you sure?")) {
        e.preventDefault()
      }
    }
  })

  // Auto-refresh results page every 30 seconds
  if (window.location.href.indexOf("indie500-results") > -1) {
    setInterval(() => {
      location.reload()
    }, 30000)
  }
})
