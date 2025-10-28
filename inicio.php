<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inicio</title>
  <link rel="icon" type="image/x-icon" href="http://ganagram.com/wp-content/uploads/2023/11/favicon-32x32-1.png">
<!--Bootstrap 5 Css -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

<!-- Nuevos cambios -->
 
<style>
    /* Ensure the card body is a flex container */
    .card-body {
        display: flex;
        flex-direction: column; /* Stack children vertically */
        justify-content: center; /* Center items vertically */
        align-items: center; /* Center items horizontally */
        height: 50vh; /* Full height of the viewport */
    }

    .img-center img, .logo-img-center img {
        max-width: 100%; /* Ensure images are responsive */
        height: auto; /* Maintain aspect ratio */
    }

    .img-center img{
        width:17.7rem;
        height:18rem;
        border-radius: 10px;
        box-shadow: 3px 3px grey, -1em 0 .4em olive;
    }
    .logo-img-center img{
        width:10rem;
        height:10rem;
        border-radius: 50%;
        box-shadow: 3px 3px grey, -1em 0 .4em olive;
    }
    h6 {
        margin-top: 20px;
        font-size: 0.8rem;
        color:red;
        text-align: center
    }
</style>
</head>
<body>
<div class="container">
  <div class="row mt-5">
    <!-- Card 1 - GANAGRAM -->
    <div class="col-6 col-md-3">
      <div class="card">
        <div class="card-body">
          <h2 class="text-center">GANAGRAM</h2>
          <div class="img-center pt-3">
          </div>
          <div class="logo-img-center pt-3 d-flex justify-content-center">
            <a href="./inventario_aviar.php">
              <img src="./images/Ganagram_New_Logo-png.png" width="50%" height="50px" alt="Ganagram Logo">
            </a>
          </div>
          <h6>Unidad Produccion Vacuna #: 01012025-3526</h6>
        </div>
      </div>
    </div>

    <!-- Card 2 - BUFAGRAM -->
    <div class="col-6 col-md-3">
      <div class="card">
        <div class="card-body">
          <h2 class="text-center">BUFAGRAM</h2>
          <div class="img-center pt-3">
          </div>
          <div class="logo-img-center pt-3 d-flex justify-content-center">
            <a href="../aviar/inventario_aviar.php">
              <img src="./images/Bufagram_logo.png" width="50%" height="50px" alt="Bufagram Logo">
            </a>
          </div>
          <h6>Unidad Produccion Bufalina #: 01012025-3527</h6>
        </div>
      </div>
    </div>

    <!-- Card 3 - PORCIGRAM -->
    <div class="col-6 col-md-3">
      <div class="card">
        <div class="card-body">
          <h2 class="text-center">PORCIGRAM</h2>
          <div class="img-center pt-3">
          </div>
          <div class="logo-img-center pt-3 d-flex justify-content-center">
            <a href="../porcino/inventario_porcino.php">
              <img src="./images/Logo_Porkygram_png.png" width="50%" height="50px" alt="Porcigram Logo">
            </a>
          </div>
          <h6>Unidad Produccion Porcina #: 01012025-3528</h6>
        </div>
      </div>
    </div>

    <!-- Card 4 - AVEGRAM -->
    <div class="col-6 col-md-3">
      <div class="card">
        <div class="card-body">
          <h2 class="text-center">AVEGRAM</h2>
          <div class="img-center pt-3">
          </div>
          <div class="logo-img-center pt-3 d-flex justify-content-center">
            <a href="../aviar/inventario_aviar.php">
              <img src="./images/Avegram_Logo.png" width="50%" height="50px" alt="Avegram Logo">
            </a>
          </div>

          <h6>Unidad Produccion Aviar #: 01012025-3529</h6>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
</body>
</html>