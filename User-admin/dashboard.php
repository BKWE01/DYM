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

// Reste du code pour la page protégée
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <style>
    .wrapper {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    .validate-btn {
      border: 2px solid #38a169;
      color: #38a169;
      padding: 8px 18px;
      border-radius: 8px;
      text-align: center;
      cursor: pointer;
      font-size: 16px;
      font-weight: 600;
      background-color: transparent;
      transition: color 0.3s, border-color 0.3s;
    }
    .validate-btn:hover {
      color: #2f855a;
      border-color: #2f855a;
    }
    .date-time {
      display: flex;
      align-items: center;
      font-size: 16px;
      color: #4a5568;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding: 8px 18px;
    }
    .date-time .material-icons {
      margin-right: 12px;
      font-size: 22px;
      color: #2d3748;
    }
    .date-time span {
      font-weight: 500;
      line-height: 1.4;
    }
    .card {
      background-color: #ffffff;
      border-radius: 8px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      padding: 16px;
      margin-bottom: 16px;
      transition: background-color 0.3s ease, box-shadow 0.3s ease;
      cursor: pointer; /* Changement du curseur en main */
    }

    .card:hover {
      background-color: #3182ce; /* Changement du fond en bleu lors du survol */
      color: #ffffff; /* Changement de la couleur du texte */
      box-shadow: 0 8px 12px rgba(0, 0, 0, 0.2);
    }

    .card a {
      color: #3182ce;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
    }

    .card:hover a {
      color: #ffffff; /* Changement de la couleur du lien au survol */
    }

    /* Styles pour le tableau */
    .bg-light-gray {
      background-color: #f5f5f5;
    }
  </style>
</head>
<body class="bg-gray-100">
  <div class="wrapper">
    <?php include_once '../components/navbar_achat.php'; ?>

    <main class="flex-1 p-6">
      <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex justify-between items-center">
        <button class="validate-btn">Tableau de bord Admin</button>
        <div class="relative flex items-center mx-4 w-1/3">
          <input type="text" placeholder="Rechercher..." class="border border-gray-300 rounded-lg pl-10 pr-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-blue-400" />
          <span class="material-icons absolute left-3 text-gray-500">search</span>
        </div>
        <div class="date-time">
          <span class="material-icons">calendar_today</span>
          <span id="date-time-display"></span>
        </div>
      </div>
      <div class="header-container mb-4 flex items-center justify-between">
                <h2 class="text-2xl font-semibold">Vue d'ensemble</h2>
                
                <a href="ensemble_expressions.php">
                    <button class="bg-white border border-gray-400 text-gray-700 font-bold py-2 px-4 rounded-lg hover:bg-gray-100">
                        Historique des expressions
                    </button>
                </a>
            </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-lg shadow">
          <h3 class="text-lg font-semibold mb-2">Aujourd'hui</h3>
          <hr>
          <br>
          <div id="today-cards-besoin" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
          <h3 class="text-lg font-semibold mb-2">Cette Semaine</h3>
          <hr>
          <div id="week-cards-besoin" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-8"></div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
          <h3 class="text-lg font-semibold mb-2">Ce Mois</h3>
          <hr>
          <div id="month-cards-besoin" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-8"></div>
        </div>
      </div>
    </main>

    <?php include_once '../components/footer.html'; ?>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    const mobileMenuButton = document.querySelector('[aria-controls="mobile-menu"]');
    const mobileMenu = document.getElementById('mobile-menu');
    mobileMenuButton.addEventListener('click', function () {
      mobileMenu.classList.toggle('hidden');
    });

    function updateDateTime() {
      const now = new Date();
      const formattedDate = `${now.toLocaleDateString()} ${now.toLocaleTimeString()}`;
      document.getElementById('date-time-display').textContent = formattedDate;
    }

    setInterval(updateDateTime, 1000); // Update time every second

    let allCards = []; // Stocke toutes les cartes

    function fetchAndDisplayCardsBesoin(period) {
      return fetch(`get_expressions_vaide.php?period=${period}`) // Remplacez par le chemin correct vers votre API
        .then(response => response.json())
        .then(data => {
          const container = document.getElementById(`${period}-cards-besoin`);
          container.innerHTML = '';
          allCards[period] = data; // Stocke les cartes par période
          data.forEach(expr => {
            const card = document.createElement('div');
            card.className = 'card';
            card.style.fontSize = '13px'; // Ajustez la taille de la police ici
            card.innerHTML = `
             Expression de besoin<br> N° ${expr.idExpression} projet ${expr.nom_client}
            `;
            // Ajouter un gestionnaire d'événement pour ouvrir dans un nouvel onglet
            card.addEventListener('click', () => {
              window.open(`generate_pdf.php?id=${expr.idExpression}`, '_blank');
            });
            container.appendChild(card);
          });
        })
        .catch(error => console.error(`Error fetching ${period} expressions besoin:`, error));
    }

    // Fonction de recherche
    function filterCards(searchTerm) {
      const allCardElements = document.querySelectorAll('.card');
      allCardElements.forEach(card => {
        const cardText = card.innerText.toLowerCase();
        if (cardText.includes(searchTerm.toLowerCase())) {
          card.style.display = ''; // Affiche la carte
        } else {
          card.style.display = 'none'; // Cache la carte
        }
      });
    }

    // Événement sur la barre de recherche
    const searchInput = document.querySelector('input[placeholder="Rechercher..."]');
    searchInput.addEventListener('input', (event) => {
      const searchTerm = event.target.value;
      filterCards(searchTerm); // Filtre les cartes
    });

    // Fetch and display expressions for each period
    fetchAndDisplayCardsBesoin('today');
    fetchAndDisplayCardsBesoin('week');
    fetchAndDisplayCardsBesoin('month');
  });
</script>

</body>
</html>
