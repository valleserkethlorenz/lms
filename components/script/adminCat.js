$(document).on("submit", "#addCatForm", function (e) {
  e.preventDefault();

  var formData = new FormData(this);
  formData.append("addCat", true);
  $.ajax({
    url: "action/catAction.php",
    type: "post",
    data: formData,
    processData: false,
    contentType: false,
    success: function (data) {
      var res = jQuery.parseJSON(data);
      $("#spinner").hide();
      $("#submitBtn").prop("disabled", false);
      if (res.status == 400) {
        Swal.fire({
          icon: "warning",
          title: "Something Went Wrong.",
          text: res.msg,
          timer: 10000,
        });
      } else if (res.status == 201) {
        Swal.fire({
          icon: "success",
          title: "SUCCESS",
          text: res.msg,
          timer: 2000,
        }).then(function () {
          location.reload();
        });
      }
    },
  });
});
$(document).on("submit", "#editCatForm", function (e) {
  e.preventDefault();

  $("#spinner").show();
  $("#submitBtn").prop("disabled", true);
  var formData = new FormData(this);
  formData.append("editCat", true);
  $.ajax({
    url: "action/catAction.php",
    type: "post",
    data: formData,
    processData: false,
    contentType: false,
    success: function (data) {
      var res = jQuery.parseJSON(data);
      $("#spinner").hide();
      $("#submitBtn").prop("disabled", false);
      if (res.status == 400) {
        Swal.fire({
          icon: "warning",
          title: "Something Went Wrong.",
          text: res.msg,
          timer: 10000,
        });
      } else if (res.status == 201) {
        Swal.fire({
          icon: "success",
          title: "SUCCESS",
          text: res.msg,
          timer: 2000,
        }).then(function () {
          location.reload();
        });
      }
    },
  });
});
$(document).on("submit", "#deleteForm", function (e) {
  e.preventDefault();
  var formData = new FormData(this);
  formData.append("removeCat", true);
  Swal.fire({
    title: "Are you sure you want to delete this?",
    showDenyButton: false,
    showCancelButton: true,
    confirmButtonText: "Submit",
  }).then((result) => {
    /* Read more about isConfirmed, isDenied below */
    if (result.isConfirmed) {
      $.ajax({
        url: "action/catAction.php",
        type: "post",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          var res = jQuery.parseJSON(response);
          if (res.status == 400) {
            Swal.fire({
              icon: "error",
              title: "Something Went Wrong.",
              text: res.msg,
              timer: 10000,
            });
          } else if (res.status == 201) {
            Swal.fire({
              icon: "success",
              title: "SUCCESS",
              text: res.msg,
              timer: 2000,
            }).then(function () {
              location.reload();
            });
          }
        },
      });
    } else if (result.isDenied) {
      Swal.fire("Changes are not saved", "", "info").then(function () {
        location.reload();
      });
    }
  });
});
