(function ($) {
  "use strict";

  var WPRI = {
    cachedPreview: null,

    init: function () {
      // Use event delegation so it works even if the metabox DOM is
      // rendered late (Gutenberg block editor loads metaboxes after
      // the main document.ready fires).
      $(document).on("click", "#wpri-preview-btn", this.handlePreview.bind(this));
      $(document).on("click", "#wpri-apply-btn", this.handleApply.bind(this));
      $(document).on("click", "#wpri-restore-btn", this.handleRestore.bind(this));
    },

    showStatus: function (message) {
      $("#wpri-status").show();
      $("#wpri-status-text").text(message);
      $("#wpri-error").hide();
    },

    hideStatus: function () {
      $("#wpri-status").hide();
    },

    showError: function (message) {
      $("#wpri-error").show();
      $("#wpri-error-text").text(message);
      this.hideStatus();
    },

    getPostId: function () {
      // Try from the button data attribute first.
      var postId = $("#wpri-preview-btn").data("post-id");

      // Fallback: get from the URL query param or the hidden #post_ID input.
      if (!postId) {
        var $input = $("#post_ID");
        if ($input.length) {
          postId = $input.val();
        }
      }

      // Fallback: parse from URL.
      if (!postId) {
        var match = window.location.search.match(/[?&]post=(\d+)/);
        if (match) {
          postId = match[1];
        }
      }

      return parseInt(postId, 10) || 0;
    },

    handlePreview: function (e) {
      e.preventDefault();
      var postId = this.getPostId();

      if (!postId) {
        this.showError(
          "Veuillez d'abord enregistrer l'article avant de prévisualiser la reformulation."
        );
        return;
      }

      this.showStatus(wpriData.i18n.previewing);
      $("#wpri-preview-area").hide();
      $("#wpri-apply-btn").hide();

      $.ajax({
        url: wpriData.ajaxUrl,
        type: "POST",
        dataType: "json",
        data: {
          action: "wpri_preview",
          nonce: wpriData.nonce,
          post_id: postId,
        },
        timeout: 120000,
        success: function (response) {
          WPRI.hideStatus();

          if (response.success) {
            WPRI.cachedPreview = response.data;
            $("#wpri-original-intro").html(response.data.original_intro);
            $("#wpri-rephrased-intro").html(response.data.rephrased_intro);
            $("#wpri-preview-area").slideDown();
            $("#wpri-apply-btn").show();
          } else {
            var msg =
              (response.data && response.data.message) ||
              wpriData.i18n.error;
            WPRI.showError(msg);
          }
        },
        error: function (xhr, status, error) {
          var msg = wpriData.i18n.error;
          if (status === "timeout") {
            msg = "La requête a expiré. L'API met trop de temps à répondre.";
          } else if (xhr.status === 0) {
            msg = "Impossible de contacter le serveur. Vérifiez votre connexion.";
          } else if (xhr.status === 403) {
            msg = "Accès refusé. Rechargez la page et réessayez.";
          } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            msg = xhr.responseJSON.data.message;
          }
          WPRI.showError(msg);
        },
      });
    },

    handleApply: function (e) {
      e.preventDefault();

      if (!confirm(wpriData.i18n.confirmApply)) {
        return;
      }

      var postId = this.getPostId();
      if (!postId) {
        this.showError("Impossible de déterminer l'ID de l'article.");
        return;
      }

      this.showStatus(wpriData.i18n.applying);

      $.ajax({
        url: wpriData.ajaxUrl,
        type: "POST",
        dataType: "json",
        data: {
          action: "wpri_apply",
          nonce: wpriData.nonce,
          post_id: postId,
        },
        timeout: 120000,
        success: function (response) {
          WPRI.hideStatus();

          if (response.success) {
            $("#wpri-preview-area").slideUp();
            $("#wpri-apply-btn").hide();

            var $notice = $(
              '<div class="notice notice-success is-dismissible" style="margin:10px 0;"><p>' +
                wpriData.i18n.success +
                "</p></div>"
            );
            $("#wpri-metabox").prepend($notice);

            setTimeout(function () {
              location.reload();
            }, 1500);
          } else {
            var msg =
              (response.data && response.data.message) ||
              wpriData.i18n.error;
            WPRI.showError(msg);
          }
        },
        error: function (xhr) {
          var msg = wpriData.i18n.error;
          if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            msg = xhr.responseJSON.data.message;
          }
          WPRI.showError(msg);
        },
      });
    },

    handleRestore: function (e) {
      e.preventDefault();
      var postId = this.getPostId();
      if (!postId) {
        this.showError("Impossible de déterminer l'ID de l'article.");
        return;
      }

      this.showStatus(wpriData.i18n.restoring);

      $.ajax({
        url: wpriData.ajaxUrl,
        type: "POST",
        dataType: "json",
        data: {
          action: "wpri_restore",
          nonce: wpriData.nonce,
          post_id: postId,
        },
        timeout: 60000,
        success: function (response) {
          WPRI.hideStatus();

          if (response.success) {
            var $notice = $(
              '<div class="notice notice-success is-dismissible" style="margin:10px 0;"><p>' +
                wpriData.i18n.restored +
                "</p></div>"
            );
            $("#wpri-metabox").prepend($notice);

            setTimeout(function () {
              location.reload();
            }, 1500);
          } else {
            var msg =
              (response.data && response.data.message) ||
              wpriData.i18n.error;
            WPRI.showError(msg);
          }
        },
        error: function (xhr) {
          var msg = wpriData.i18n.error;
          if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            msg = xhr.responseJSON.data.message;
          }
          WPRI.showError(msg);
        },
      });
    },
  };

  // Initialize immediately AND on document ready to cover both
  // classic editor and Gutenberg timing.
  $(document).ready(function () {
    WPRI.init();
  });
})(jQuery);
