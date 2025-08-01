// Indie500 Manager Frontend JavaScript
const $ = window.jQuery // Declare jQuery variable
const indie500_ajax = window.indie500_ajax // Declare indie500_ajax variable

$(document).ready(($) => {
  const maxVotes = Number.parseInt($("#indie500-selected-count").text().split("/")[1]) || 10
  let selectedCount = 0

  // Search functionality
  $("#indie500-search").on("input", function () {
    const searchTerm = $(this).val().toLowerCase()
    filterSongs()
  })

  // Year filter functionality
  $("#indie500-year-filter").on("change", () => {
    filterSongs()
  })

  function filterSongs() {
    const searchTerm = $("#indie500-search").val().toLowerCase()
    const selectedYear = $("#indie500-year-filter").val()

    $(".indie500-song-item").each(function () {
      const $item = $(this)
      const searchData = $item.data("search") || ""
      const yearData = $item.data("year") || ""

      const matchesSearch = !searchTerm || searchData.includes(searchTerm)
      const matchesYear = !selectedYear || yearData == selectedYear

      if (matchesSearch && matchesYear) {
        $item.removeClass("indie500-hidden")
      } else {
        $item.addClass("indie500-hidden")
      }
    })
  }

  // Vote checkbox handling
  $(document).on("change", ".indie500-vote-checkbox", function () {
    updateVoteCount()

    if ($(this).is(":checked") && selectedCount > maxVotes) {
      $(this).prop("checked", false)
      showMessage(indie500_ajax.messages.max_votes, "error")
      updateVoteCount()
    }
  })

  function updateVoteCount() {
    selectedCount = $(".indie500-vote-checkbox:checked").length
    $("#indie500-selected-count").text(selectedCount)

    if (selectedCount > 0) {
      $("#indie500-submit-btn").prop("disabled", false)
    } else {
      $("#indie500-submit-btn").prop("disabled", true)
    }

    // Visual feedback for max votes
    if (selectedCount >= maxVotes) {
      $(".indie500-vote-counter").addClass("indie500-max-reached")
    } else {
      $(".indie500-vote-counter").removeClass("indie500-max-reached")
    }
  }

  // Form submission
  $("#indie500-vote-form").on("submit", (e) => {
    e.preventDefault()

    const selectedVotes = []
    $(".indie500-vote-checkbox:checked").each(function () {
      selectedVotes.push($(this).val())
    })

    if (selectedVotes.length === 0) {
      showMessage(indie500_ajax.messages.no_selection, "error")
      return
    }

    if (selectedVotes.length > maxVotes) {
      showMessage(indie500_ajax.messages.max_votes, "error")
      return
    }

    // Disable form during submission
    $("#indie500-submit-btn").prop("disabled", true).text("Submitting...")
    $(".indie500-vote-checkbox").prop("disabled", true)

    $.ajax({
      url: indie500_ajax.ajax_url,
      type: "POST",
      data: {
        action: "indie500_submit_vote",
        nonce: indie500_ajax.nonce,
        votes: selectedVotes,
      },
      success: (response) => {
        if (response.success) {
          showMessage(response.data, "success")
          $("#indie500-vote-form").hide()
        } else {
          showMessage(response.data, "error")
          // Re-enable form
          $("#indie500-submit-btn").prop("disabled", false).text("Submit Votes")
          $(".indie500-vote-checkbox").prop("disabled", false)
        }
      },
      error: () => {
        showMessage(indie500_ajax.messages.vote_error, "error")
        // Re-enable form
        $("#indie500-submit-btn").prop("disabled", false).text("Submit Votes")
        $(".indie500-vote-checkbox").prop("disabled", false)
      },
    })
  })

  function showMessage(message, type) {
    const $messageDiv = $("#indie500-message")
    $messageDiv
      .removeClass("indie500-success indie500-error")
      .addClass("indie500-" + type)
      .text(message)
      .show()

    // Auto-hide after 5 seconds
    setTimeout(() => {
      $messageDiv.fadeOut()
    }, 5000)

    // Scroll to message
    $("html, body").animate(
      {
        scrollTop: $messageDiv.offset().top - 100,
      },
      500,
    )
  }
})

// Indie500 Voting Page (vanilla JS, no frameworks)
document.addEventListener('DOMContentLoaded', function () {
  if (typeof window.indie500VotingSongs === 'undefined' || typeof window.indie500VotingConfig === 'undefined') return;
  const songs = window.indie500VotingSongs;
  const config = window.indie500VotingConfig;
  let currentPage = 1;
  let selected = new Set();

  const wrapper = document.getElementById('indie500-voting-list-wrapper');
  const selectedCountSpan = document.getElementById('indie500-voting-selected-count');
  const submitBtn = document.getElementById('indie500-voting-submit-btn');
  const form = document.getElementById('indie500-voting-form');
  const messageDiv = document.getElementById('indie500-voting-message');
  const thankyouDiv = document.getElementById('indie500-voting-thankyou');

  function renderPage(page) {
    currentPage = page;
    const start = (page - 1) * config.perPage;
    const end = start + config.perPage;
    const pageSongs = songs.slice(start, end);
    let html = '<div class="indie500-voting-list">';
    html += '<div class="indie500-voting-list-header-row">'
      + '<div class="indie500-voting-list-col indie500-voting-rank-col">#</div>'
      + '<div class="indie500-voting-list-col indie500-voting-artist-col">Artiest</div>'
      + '<div class="indie500-voting-list-col indie500-voting-title-col">Titel</div>'
      + '<div class="indie500-voting-list-col indie500-voting-checkbox-col"></div>'
      + '</div>';
    pageSongs.forEach(function (song, i) {
      const globalRank = song.rank;
      const checked = selected.has(String(song.id)) ? 'checked' : '';
      const rowClass = (i % 2 === 0 ? 'indie500-voting-list-row-even' : 'indie500-voting-list-row-odd') + (globalRank <= 10 ? ' indie500-voting-list-top10' : '');
      html += '<div class="indie500-voting-list-row ' + rowClass + '">' +
        '<div class="indie500-voting-list-col indie500-voting-rank-col">#' + globalRank + '</div>' +
        '<div class="indie500-voting-list-col indie500-voting-artist-col">' + escapeHtml(song.artist) + '</div>' +
        '<div class="indie500-voting-list-col indie500-voting-title-col">' + escapeHtml(song.title) + '</div>' +
        '<div class="indie500-voting-list-col indie500-voting-checkbox-col">' +
          '<input type="checkbox" class="indie500-voting-checkbox" value="' + song.id + '" ' + checked + ' />' +
        '</div>' +
      '</div>';
    });
    html += '</div>';
    // Pagination
    if (config.totalPages > 1) {
      html += '<div class="indie500-voting-pagination">';
      for (let p = 1; p <= config.totalPages; p++) {
        html += '<button type="button" class="indie500-voting-page-btn' + (p === currentPage ? ' active' : '') + '" data-page="' + p + '">' + p + '</button>';
      }
      html += '</div>';
    }
    wrapper.innerHTML = html;
    // Add event listeners
    wrapper.querySelectorAll('.indie500-voting-checkbox').forEach(function (cb) {
      cb.addEventListener('change', onCheckboxChange);
    });
    wrapper.querySelectorAll('.indie500-voting-page-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        renderPage(Number(btn.getAttribute('data-page')));
      });
    });
  }

  function onCheckboxChange(e) {
    const val = e.target.value;
    if (e.target.checked) {
      if (selected.size >= config.maxVotes) {
        e.target.checked = false;
        showMessage('Je mag maximaal ' + config.maxVotes + ' titels selecteren.', 'error');
        return;
      }
      selected.add(val);
    } else {
      selected.delete(val);
    }
    updateSelectedCount();
  }

  function updateSelectedCount() {
    selectedCountSpan.textContent = selected.size;
    submitBtn.disabled = selected.size === 0;
  }

  function showMessage(msg, type) {
    messageDiv.textContent = msg;
    messageDiv.className = 'indie500-message indie500-' + (type || 'error');
    messageDiv.style.display = 'block';
    setTimeout(function () { messageDiv.style.display = 'none'; }, 4000);
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (selected.size === 0) {
      showMessage('Selecteer minimaal 1 titel.', 'error');
      return;
    }
    if (selected.size > config.maxVotes) {
      showMessage('Je mag maximaal ' + config.maxVotes + ' titels selecteren.', 'error');
      return;
    }
    submitBtn.disabled = true;
    submitBtn.textContent = 'Bezig met stemmen...';
    // AJAX submit
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.indie500_ajax.ajax_url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function () {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Stemmen Verzenden';
      if (xhr.status === 200) {
        try {
          var resp = JSON.parse(xhr.responseText);
          if (resp.success) {
            form.style.display = 'none';
            thankyouDiv.style.display = 'block';
          } else {
            showMessage(resp.data || 'Er ging iets mis bij het stemmen.', 'error');
          }
        } catch (e) {
          showMessage('Er ging iets mis bij het stemmen.', 'error');
        }
      } else {
        showMessage('Er ging iets mis bij het stemmen.', 'error');
      }
    };
    var params = 'action=indie500_submit_vote&nonce=' + encodeURIComponent(window.indie500_ajax.nonce);
    selected.forEach(function (id) { params += '&votes[]=' + encodeURIComponent(id); });
    xhr.send(params);
  });

  function escapeHtml(str) {
    return String(str).replace(/[&<>"]/g, function (c) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
    });
  }

  // Initial render
  renderPage(1);
  updateSelectedCount();
});
