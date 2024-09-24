jQuery(document).ready(function ($) {
  // Trigger the AJAX request when the button is clicked
  $("#huuto-sync-button").on("click", function (e) {
    e.preventDefault();

    var postId = $(this).data("post-id"); // Get the product ID
    var statusArea = $("#huuto-sync-status"); // Get the status message area

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
        console.log("Full Response Object: ", response); // Log full response object
        if (response.success) {
          console.log("Huuto Response:", response.data); // Check if response data exists
          $("#huuto-sync-status").html(
            '<span style="color:green;">' + response.data.message + "</span>"
          );
        } else {
          $("#huuto-sync-status").html(
            '<span style="color:red;">' + response.data + "</span>"
          );
        }
      },
      error: function (e) {
        console.log("Error:", e);
        statusArea.html(
          '<span style="color:red;">An error occurred while syncing.</span>'
        );
      },
    });
  });
});
