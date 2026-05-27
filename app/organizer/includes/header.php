<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">
  <title>ORDERING [ADMIN]</title>

  <link rel="icon" type="image/x-icon" href="../../vendor/images/logo.png">

  <!-- Custom fonts for this template-->
  <!-- Custom styles for this template-->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

  <!-- database table -->
  <link rel="stylesheet" type="text/css" href=" ../../vendor/datatables/css/dataTables.bootstrap4.css">
  <link rel="stylesheet" type="text/css" href=" ../../vendor/datatables/css/buttons.bootstrap4.css">
  <link rel="stylesheet" type="text/css" href=" ../../vendor/datatables/css/select.bootstrap4.css">
  <link rel="stylesheet" type="text/css" href=" ../../vendor/datatables/css/fixedHeader.bootstrap4.css">
  <link rel="stylesheet" type="text/css" href=" ../../vendor/alert/bootstrap.min.css">
  <link rel="stylesheet" type="text/css" href=" ../../vendor/alert/alertify.min.css">
</head>

<link href="../../vendor/css/sb-admin-2.min.css" rel="stylesheet">
<style>
  .colored-toast.swal2-icon-success {
    background-color: #3d8514 !important;
    color: white;

  }

  .swal2-timer-progress-bar {
    background: #ffffff !important;
    /* any color you want */
  }


  .colored-toast.swal2-icon-error {
    background-color: red !important;
    color: white;
  }

  .dashboards {
    border-radius: 10px;
    box-shadow: 3px 3px 5px 3px rgba(7, 61, 2, 0.5);
    border-style: outset;
    border-left: 4px solid grey;
    border-right: 1px solid black;
    border-bottom: 1px solid black;
    border-top: 1px solid black;
  }

  .graphs {
    border-radius: 10px;
    color: black;
    box-shadow: 5px 5px 4px 3px rgba(41, 41, 44, 0.8);
    border-left: 5px solid grey;
  }

  /* Card titles */
  .card.dashboards h5 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 10px;
  }

  /* Adjust numbers on small screens */
  @media (max-width: 576px) {
    .card.dashboards h1 {
      font-size: 1.5rem !important;
    }

    .card.dashboards img {
      width: 30px;
    }
  }

  .card-headers {
    border-radius: 10px;
    background-color: grey;
  }

  @font-face {
    font-family: head;
    src: url(../../vendor/fonts/Poppins-Regular.ttf);
  }

  * {
    font-family: head;
  }

  body {
    background-color: #D3D3D3;
  }

  .modal-header {
    background-color: #09173c;
    /* color: #cbd99b; */
    color: white;
  }

  .form-label {
    color: black;
    font-weight: bolder;
  }

  .form-control {
    color: black;
    font-weight: bolder;
  }

  .form-label {
    text-transform: uppercase;
  }

  .cards {
    border-radius: 15px;
    box-shadow: 2px 7px 10px 4px rgba(0, 0, 0, 0.3);
  }
</style>