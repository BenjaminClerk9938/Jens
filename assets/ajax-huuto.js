jQuery(document).ready(function ($) {
  // Trigger the AJAX request when the button is clicked
  $("#huuto-sync-button").on("click", function (e) {
    e.preventDefault();

    var postId = $(this).data("post-id"); // Get the product ID

    // Start the AJAX request
    $.ajax({
      url: ajaxurl, // Use WordPress's built-in ajaxurl variable
      type: "POST",
      data: {
        action: "sync_product_to_huuto", // The AJAX action to trigger
        post_id: postId, // Pass the product ID
        security: huuto_ajax_obj.nonce, // Security nonce for validation
      },
      beforeSend: function () {
        $("#huuto-sync-status").html("Syncing..."); // Show a status message
      },
      success: function (response) {
        if (response.success) {
          $("#huuto-sync-status").html("Success: " + response.data); // Show success message
        } else {
          $("#huuto-sync-status").html("Error: " + response.data); // Show error message
        }
      },
      error: function (xhr, status, error) {
        $("#huuto-sync-status").html("AJAX Error: " + error); // Show AJAX error message
      },
    });
  });
});
