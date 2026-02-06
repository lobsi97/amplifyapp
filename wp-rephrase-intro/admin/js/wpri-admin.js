(function ($) {
  "use strict";

  var WPRI = {
    cachedPreview: null,

    init: function () {
      $("#wpri-preview-btn").on("click", this.handlePreview.bind(this));
      $("#wpri-apply-btn").on("click", this.handleApply.bind(this));
      $("#wpri-restore-btn").on("click", this.handleRestore.bind(this));
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

    handlePreview: function (e) {
      e.preventDefault();
      var postId = $("#wpri-preview-btn").data("post-id");

      this.showStatus(wpriData.i18n.previewing);
      $("#wpri-preview-area").hide();
      $("#wpri-apply-btn").hide();

      $.ajax({
        url: wpriData.ajaxUrl,
        type: "POST",
        data: {
          action: "wpri_preview",
          nonce: wpriData.nonce,
          post_id: postId,
        },
        success: function (response) {
          WPRI.hideStatus();

          if (response.success) {
            WPRI.cachedPreview = response.data;
            $("#wpri-original-intro").html(response.data.original_intro);
            $("#wpri-rephrased-intro").html(response.data.rephrased_intro);
            $("#wpri-preview-area").slideDown();
            $("#wpri-apply-btn").show();
          } else {
            WPRI.showError(
              response.data.message || wpriData.i18n.error
            );
          }
        },
        error: function () {
          WPRI.showError(wpriData.i18n.error);
        },
      });
    },

    handleApply: function (e) {
      e.preventDefault();

      if (!confirm(wpriData.i18n.confirmApply)) {
        return;
      }

      var postId = $("#wpri-apply-btn").data("post-id");
      this.showStatus(wpriData.i18n.applying);

      $.ajax({
        url: wpriData.ajaxUrl,
        type: "POST",
        data: {
          action: "wpri_apply",
          nonce: wpriData.nonce,
          post_id: postId,
        },
        success: function (response) {
          WPRI.hideStatus();

          if (response.success) {
            $("#wpri-preview-area").slideUp();
            $("#wpri-apply-btn").hide();

            // Show success notice.
            var $notice = $(
              '<div class="notice notice-success is-dismissible" style="margin:10px 0;"><p>' +
                wpriData.i18n.success +
                "</p></div>"
            );
            $("#wpri-metabox").prepend($notice);

            // Reload to show updated content after a short delay.
            setTimeout(function () {
              location.reload();
            }, 1500);
          } else {
            WPRI.showError(
              response.data.message || wpriData.i18n.error
            );
          }
        },
        error: function () {
          WPRI.showError(wpriData.i18n.error);
        },
      });
    },

    handleRestore: function (e) {
      e.preventDefault();
      var postId = $("#wpri-restore-btn").data("post-id");

      this.showStatus(wpriData.i18n.restoring);

      $.ajax({
        url: wpriData.ajaxUrl,
        type: "POST",
        data: {
          action: "wpri_restore",
          nonce: wpriData.nonce,
          post_id: postId,
        },
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
            WPRI.showError(
              response.data.message || wpriData.i18n.error
            );
          }
        },
        error: function () {
          WPRI.showError(wpriData.i18n.error);
        },
      });
    },
  };

  $(document).ready(function () {
    WPRI.init();
  });
})(jQuery);
