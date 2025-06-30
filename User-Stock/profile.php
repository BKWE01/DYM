<?php
session_start();

// Désactiver la mise en cache de la page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Rediriger vers index.php
    header("Location: ./../index.php");
    exit();
}

// Inclure la connexion et démarrer la session
include_once '../database/connection.php'; 

// Assurez-vous que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirige vers la page de connexion si l'utilisateur n'est pas connecté
    exit();
}

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users_exp WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérifier si les données utilisateur ont été récupérées
if (!$user) {
    die('Utilisateur non trouvé');
}

$name = isset($user['name']) ? htmlspecialchars($user['name']) : 'Nom non défini';
$email = isset($user['email']) ? htmlspecialchars($user['email']) : 'Email non défini';
$profile_image = isset($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'default-profile.png'; // Assurez-vous de définir une image par défaut
$signature = isset($user['signature']) ? htmlspecialchars($user['signature']) : 'default-signature.png'; // Assurez-vous de définir une signature par défaut
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <style>
    body, html {
      height: 100%;
      margin: 0;
    }
    .wrapper {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }
    .content {
      flex: 1;
    }
    .upload-area {
      border: 2px dashed #1d4ed8;
      border-radius: 8px;
      padding: 20px;
      text-align: center;
      background-color: #f9fafb;
    }
    .upload-area:hover {
      background-color: #e2e8f0;
    }
    .upload-icon {
      font-size: 48px;
      color: #1d4ed8;
    }
  </style>
</head>
<body class="bg-gray-200">

  <div class="wrapper">
    <!-- Include Navbar -->
    <?php include_once '../components/navbar_stock.php'; ?>

    <main class="content flex-1 p-6">
      <!-- Main Content -->
      <div class="max-w-4xl mx-auto bg-gray-100 p-8 rounded-lg">
        <div class="flex items-start justify-between">
          <div class="flex items-center space-x-6">
            <img id="profile-image" class="profile-image h-32 w-32 rounded-full" src="../uploads/<?php echo $profile_image; ?>" alt="Profile Image">
            <div>
              <h1 class="text-3xl font-semibold text-gray-800"><?php echo $name; ?></h1>
              <p class="text-lg text-gray-600"><?php echo $email; ?></p>
            </div>
          </div>
          <div>
            <img id="signature-image" class="h-24" src="../uploads/<?php echo $signature; ?>" alt="Signature Image">
          </div>
        </div>

        <div class="mt-8 grid grid-cols-2 gap-8">
          <div>
            <h2 class="text-xl font-medium text-gray-800">Ajouter une photo</h2>
            <form id="upload-profile-form" class="mt-4">
              <div class="upload-area">
                <span class="material-icons upload-icon">upload</span>
                <input type="file" id="profile_image" name="profile_image" class="hidden">
                <label for="profile_image" class="block mt-2 text-sm font-medium text-gray-700 cursor-pointer">Cliquez ou Glissez pour télécharger</label>
              </div>
            </form>
          </div>
          <div>
            <h2 class="text-xl font-medium text-gray-800">Télécharger une signature</h2>
            <form id="upload-signature-form" class="mt-4">
              <div class="upload-area">
                <span class="material-icons upload-icon">edit</span>
                <input type="file" id="signature" name="signature" class="hidden">
                <label for="signature" class="block mt-2 text-sm font-medium text-gray-700 cursor-pointer">Cliquez ou Glissez pour télécharger</label>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>

    <!-- Include Footer -->
    <?php include_once '../components/footer.html'; ?>
  </div>

  <!-- Scripts -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      document.getElementById('profile_image').addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
          const formData = new FormData();
          formData.append('profile_image', file);
          
          fetch('upload_profile_image.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              document.getElementById('profile-image').src = `../uploads/${data.image}`;
              
              setTimeout(() => {
                window.location.reload();
              }, 2000);
            } else {
              console.error('Erreur:', data.error);
            }
          })
          .catch(error => console.error('Erreur:', error));
        }
      });

      document.getElementById('signature').addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
          const formData = new FormData();
          formData.append('signature', file);
          
          fetch('upload_signature.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              document.getElementById('signature-image').src = `../uploads/${data.signature}`;
              
              setTimeout(() => {
                window.location.reload();
              }, 2000);
            } else {
              console.error('Erreur:', data.error);
            }
          })
          .catch(error => console.error('Erreur:', error));
        }
      });
    });
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const mobileMenuButton = document.querySelector('[aria-controls="mobile-menu"]');
      const mobileMenu = document.getElementById('mobile-menu');
      mobileMenuButton.addEventListener('click', function () {
        mobileMenu.classList.toggle('hidden');
      });
    });
  </script>
</body>
</html>
