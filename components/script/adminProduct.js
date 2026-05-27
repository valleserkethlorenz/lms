$(document).on("submit", "#addProdForm", function (e) {
  e.preventDefault();

  var formData = new FormData(this);
  formData.append("addProd", true);
  $.ajax({
    url: "action/prodAction.php",
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
$(document).on("submit", "#editProdForm", function (e) {
  e.preventDefault();

  $("#spinner").show();
  $("#submitBtn").prop("disabled", true);
  var formData = new FormData(this);
  formData.append("editProd", true);
  $.ajax({
    url: "action/prodAction.php",
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
