<?php
session_start();

// Désactiver la mise en cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../../index.php");
    exit();
}

// Connexion à la base de données
include_once '../../database/connection.php';
include_once '../../include/date_helper.php';

// Récupérer le projet sélectionné (si présent)
$selectedClient = isset($_GET['client']) ? $_GET['client'] : 'all';
$selectedCodeProjet = isset($_GET['code_projet']) ? $_GET['code_projet'] : null;

// Fonction pour formater les nombres
function formatNumber($number)
{
    return number_format($number, 0, ',', ' ');
}

try {
    // Récupérer la liste des clients et projets
    $clientsQuery = "SELECT DISTINCT nom_client 
                    FROM identification_projet 
                    WHERE " . getFilteredDateCondition('created_at') . "
                    ORDER BY nom_client";
    $clientsStmt = $pdo->prepare($clientsQuery);
    $clientsStmt->execute();
    $clients = $clientsStmt->fetchAll(PDO::FETCH_COLUMN);

    // Si un client est sélectionné, récupérer ses projets
    if ($selectedClient != 'all') {
        $projetsQuery = "SELECT code_projet, description_projet
                        FROM identification_projet
                        WHERE nom_client = :nom_client
                        AND " . getFilteredDateCondition('created_at') . "
                        ORDER BY created_at DESC";
        $projetsStmt = $pdo->prepare($projetsQuery);
        $projetsStmt->bindParam(':nom_client', $selectedClient);
        $projetsStmt->execute();
        $projets = $projetsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Construction des conditions de filtre
    $clientCondition = ($selectedClient != 'all') ? "AND ip.nom_client = :nom_client" : "";
    $projetCondition = (!empty($selectedCodeProjet)) ? "AND ip.code_projet = :code_projet" : "";

    // Statistiques générales des projets
    $statsQuery = "SELECT 
                    COUNT(DISTINCT ip.id) as total_projects,
                    COUNT(DISTINCT ed.id) as total_items,
                    COALESCE(SUM(ed.qt_acheter * ed.prix_unitaire), 0) as total_amount,
                    ROUND(AVG(DATEDIFF(CURDATE(), ip.created_at)), 0) as avg_duration
                  FROM identification_projet ip
                  LEFT JOIN expression_dym ed ON ip.idExpression = ed.idExpression
                  WHERE " . getFilteredDateCondition('ip.created_at') . "
                  $clientCondition $projetCondition";

    $statsStmt = $pdo->prepare($statsQuery);
    if ($selectedClient != 'all') {
        $statsStmt->bindParam(':nom_client', $selectedClient);
    }
    if (!empty($selectedCodeProjet)) {
        $statsStmt->bindParam(':code_projet', $selectedCodeProjet);
    }
    $statsStmt->execute();
    $statsProjects = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Récupérer le statut des achats des projets
    $statusQuery = "SELECT 
                     CASE 
                       WHEN ed.valide_achat = 'validé' THEN 'Commandé'
                       WHEN ed.valide_achat = 'reçu' THEN 'Reçu'
                       ELSE 'En attente'
                     END as status,
                     COUNT(*) as count,
                     COALESCE(SUM(ed.qt_acheter * ed.prix_unitaire), 0) as amount
                   FROM identification_projet ip
                   LEFT JOIN expression_dym ed ON ip.idExpression = ed.idExpression
                   WHERE ed.qt_acheter > 0 
                   AND " . getFilteredDateCondition('ip.created_at') . "
                   $clientCondition $projetCondition
                   GROUP BY status";

    $statusStmt = $pdo->prepare($statusQuery);
    if ($selectedClient != 'all') {
        $statusStmt->bindParam(':nom_client', $selectedClient);
    }
    if (!empty($selectedCodeProjet)) {
        $statusStmt->bindParam(':code_projet', $selectedCodeProjet);
    }
    $statusStmt->execute();
    $statusStats = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater les données de statut pour le graphique
    $statusChartData = [
        'labels' => [],
        'data' => [],
        'backgroundColor' => [
            'rgba(239, 68, 68, 0.7)',   // Rouge pour En attente
            'rgba(59, 130, 246, 0.7)',  // Bleu pour Commandé
            'rgba(16, 185, 129, 0.7)'   // Vert pour Reçu
        ]
    ];

    $statusesFound = [
        'En attente' => false,
        'Commandé' => false,
        'Reçu' => false
    ];

    foreach ($statusStats as $stat) {
        $statusesFound[$stat['status']] = true;
        $statusChartData['labels'][] = $stat['status'];
        $statusChartData['data'][] = (float) $stat['amount'];
    }

    // Ajouter les statuts manquants avec des valeurs nulles pour le graphique
    foreach ($statusesFound as $status => $found) {
        if (!$found) {
            $statusChartData['labels'][] = $status;
            $statusChartData['data'][] = 0;
        }
    }

    // Récupérer les 10 derniers projets
    $recentProjectsQuery = "SELECT 
                            ip.id,
                            ip.code_projet,
                            ip.nom_client,
                            ip.description_projet,
                            ip.chefprojet,
                            ip.created_at,
                            COUNT(ed.id) as total_items,
                            COALESCE(SUM(ed.qt_acheter * ed.prix_unitaire), 0) as total_amount,
                            SUM(CASE WHEN ed.valide_achat = 'validé' OR ed.valide_achat = 'reçu' THEN 1 ELSE 0 END) as completed_items
                           FROM identification_projet ip
                           LEFT JOIN expression_dym ed ON ip.idExpression = ed.idExpression
                           WHERE " . getFilteredDateCondition('ip.created_at') . "
                           $clientCondition
                           GROUP BY ip.id, ip.code_projet, ip.nom_client, ip.description_projet, ip.chefprojet, ip.created_at
                           ORDER BY ip.created_at DESC
                           LIMIT 10";

    $recentProjectsStmt = $pdo->prepare($recentProjectsQuery);
    if ($selectedClient != 'all') {
        $recentProjectsStmt->bindParam(':nom_client', $selectedClient);
    }
    $recentProjectsStmt->execute();
    $recentProjects = $recentProjectsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Si un projet spécifique est sélectionné, récupérer les détails des matériaux
    if (!empty($selectedCodeProjet)) {
        $materialsQuery = "SELECT 
                           ed.designation,
                           ed.quantity,
                           ed.unit,
                           ed.qt_stock,
                           ed.qt_acheter,
                           ed.prix_unitaire,
                           ed.fournisseur,
                           ed.valide_achat as status,
                           ed.created_at
                          FROM identification_projet ip
                          JOIN expression_dym ed ON ip.idExpression = ed.idExpression
                          WHERE ip.code_projet = :code_projet
                          AND " . getFilteredDateCondition('ip.created_at') . "
                          ORDER BY ed.created_at DESC";

        $materialsStmt = $pdo->prepare($materialsQuery);
        $materialsStmt->bindParam(':code_projet', $selectedCodeProjet);
        $materialsStmt->execute();
        $projectMaterials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les informations du projet sélectionné
        $projectInfoQuery = "SELECT *
                            FROM identification_projet
                            WHERE code_projet = :code_projet
                            AND " . getFilteredDateCondition('created_at') . "
                            LIMIT 1";

        $projectInfoStmt = $pdo->prepare($projectInfoQuery);
        $projectInfoStmt->bindParam(':code_projet', $selectedCodeProjet);
        $projectInfoStmt->execute();
        $projectInfo = $projectInfoStmt->fetch(PDO::FETCH_ASSOC);
    }

    // Récupérer les statistiques par catégorie pour le client/projet sélectionné
    $categoriesQuery = "SELECT 
                        c.libelle as category,
                        COUNT(ed.id) as item_count,
                        COALESCE(SUM(ed.qt_acheter * ed.prix_unitaire), 0) as amount
                       FROM expression_dym ed
                       JOIN identification_projet ip ON ed.idExpression = ip.idExpression
                       LEFT JOIN products p ON ed.designation = p.product_name
                       LEFT JOIN categories c ON p.category = c.id
                       WHERE " . getFilteredDateCondition('ip.created_at') . "
                       $clientCondition $projetCondition
                       AND ed.qt_acheter > 0
                       GROUP BY c.libelle
                       ORDER BY amount DESC";

    $categoriesStmt = $pdo->prepare($categoriesQuery);
    if ($selectedClient != 'all') {
        $categoriesStmt->bindParam(':nom_client', $selectedClient);
    }
    if (!empty($selectedCodeProjet)) {
        $categoriesStmt->bindParam(':code_projet', $selectedCodeProjet);
    }
    $categoriesStmt->execute();
    $categoriesStats = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater les données pour le graphique de catégories
    $categoriesChartData = [
        'labels' => [],
        'data' => [],
        'backgroundColor' => []
    ];

    // Couleurs pour les catégories
    $colors = [
        '#4299E1', // blue-500
        '#48BB78', // green-500
        '#ECC94B', // yellow-500
        '#9F7AEA', // purple-500
        '#ED64A6', // pink-500
        '#F56565', // red-500
        '#667EEA', // indigo-500
        '#ED8936', // orange-500
        '#38B2AC', // teal-500
        '#CBD5E0'  // gray-400
    ];

    foreach ($categoriesStats as $index => $cat) {
        $categoryName = $cat['category'] ?? 'Non catégorisé';
        $categoriesChartData['labels'][] = $categoryName;
        $categoriesChartData['data'][] = (float) $cat['amount'];
        $categoriesChartData['backgroundColor'][] = $colors[$index % count($colors)];
    }

} catch (PDOException $e) {
    $errorMessage = "Erreur lors de la récupération des statistiques: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques des Projets | Service Achat</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-100">
    <div class="wrapper">
        <?php include_once '../../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <div class="bg-white shadow-sm rounded-lg p-4 mb-6 flex flex-wrap justify-between items-center">
                <div class="flex items-center mb-2 md:mb-0">
                    <a href="index.php" class="text-blue-600 hover:text-blue-800 mr-2">
                        <span class="material-icons">arrow_back</span>
                    </a>
                    <h1 class="text-xl font-bold text-gray-800">Statistiques des Projets</h1>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <!-- Filtre par client -->
                    <form action="" method="GET" class="flex items-center gap-2">
                        <label for="client-select" class="text-gray-700">Client:</label>
                        <select id="client-select" name="client"
                            class="bg-white border border-gray-300 rounded-md py-1 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            onchange="this.form.submit()">
                            <option value="all" <?php echo $selectedClient == 'all' ? 'selected' : ''; ?>>Tous les clients
                            </option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo htmlspecialchars($client); ?>" <?php echo $selectedClient == $client ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if ($selectedClient != 'all' && !empty($projets)): ?>
                            <label for="projet-select" class="ml-2 text-gray-700">Projet:</label>
                            <select id="projet-select" name="code_projet"
                                class="bg-white border border-gray-300 rounded-md py-1 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                onchange="this.form.submit()">
                                <option value="">Tous les projets</option>
                                <?php foreach ($projets as $projet): ?>
                                    <option value="<?php echo htmlspecialchars($projet['code_projet']); ?>" <?php echo $selectedCodeProjet == $projet['code_projet'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($projet['code_projet']); ?> -
                                        <?php echo htmlspecialchars(substr($projet['description_projet'], 0, 30)) . (strlen($projet['description_projet']) > 30 ? '...' : ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </form>

                    <button id="export-pdf"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded flex items-center">
                        <span class="material-icons mr-2">picture_as_pdf</span>
                        Exporter PDF
                    </button>

                    <div class="flex items-center bg-gray-100 px-4 py-2 rounded">
                        <span class="material-icons mr-2">event</span>
                        <span id="date-time-display"></span>
                    </div>
                </div>
            </div>

            <?php if (isset($errorMessage)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <p><?php echo $errorMessage; ?></p>
                </div>
            <?php else: ?>
                <!-- Statistiques globales -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Nombre de projets -->
                    <div class="stats-card bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Projets</p>
                                <h3 class="text-2xl font-bold mt-1">
                                    <?php echo formatNumber($statsProjects['total_projects']); ?>
                                </h3>
                            </div>
                            <div class="rounded-full bg-blue-100 p-3">
                                <span class="material-icons text-blue-600">business</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            <?php
                            if ($selectedClient != 'all') {
                                echo "Projets pour " . htmlspecialchars($selectedClient);
                            } else {
                                echo "Nombre total de projets";
                            }
                            ?>
                        </p>
                    </div>

                    <!-- Nombre d'articles -->
                    <div class="stats-card bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Articles</p>
                                <h3 class="text-2xl font-bold mt-1">
                                    <?php echo formatNumber($statsProjects['total_items']); ?>
                                </h3>
                            </div>
                            <div class="rounded-full bg-green-100 p-3">
                                <span class="material-icons text-green-600">inventory</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Nombre total d'articles</p>
                    </div>

                    <!-- Montant total -->
                    <div class="stats-card bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Montant total</p>
                                <h3 class="text-2xl font-bold mt-1">
                                    <?php echo formatNumber($statsProjects['total_amount']); ?> FCFA
                                </h3>
                            </div>
                            <div class="rounded-full bg-purple-100 p-3">
                                <span class="material-icons text-purple-600">payments</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Valeur des achats</p>
                    </div>

                    <!-- Durée moyenne -->
                    <div class="stats-card bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Durée moyenne</p>
                                <h3 class="text-2xl font-bold mt-1">
                                    <?php echo formatNumber($statsProjects['avg_duration']); ?> jours
                                </h3>
                            </div>
                            <div class="rounded-full bg-yellow-100 p-3">
                                <span class="material-icons text-yellow-600">schedule</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Durée depuis création</p>
                    </div>
                </div>

                <?php if (!empty($selectedCodeProjet) && !empty($projectInfo)): ?>
                    <!-- Détails du projet sélectionné -->
                    <div class="bg-white p-6 shadow-sm rounded-lg mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold">Détails du projet:
                                <?php echo htmlspecialchars($projectInfo['code_projet']); ?></h2>
                            <div class="flex space-x-2 items-center">
                                <a href="projet_details.php?code_projet=<?php echo urlencode($projectInfo['code_projet']); ?>"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm flex items-center">
                                    <span class="material-icons text-sm mr-1">visibility</span>
                                    Voir tous les détails
                                </a>
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium rounded-full px-2.5 py-1">
                                    ID: <?php echo htmlspecialchars($projectInfo['idExpression']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-700">Client</h3>
                                <p class="text-gray-900"><?php echo htmlspecialchars($projectInfo['nom_client']); ?></p>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-700">Chef de projet</h3>
                                <p class="text-gray-900"><?php echo htmlspecialchars($projectInfo['chefprojet']); ?></p>
                            </div>
                            <div class="md:col-span-2">
                                <h3 class="text-sm font-semibold text-gray-700">Description</h3>
                                <p class="text-gray-900"><?php echo htmlspecialchars($projectInfo['description_projet']); ?></p>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-700">Localisation</h3>
                                <p class="text-gray-900"><?php echo htmlspecialchars($projectInfo['sitgeo']); ?></p>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-700">Date de création</h3>
                                <p class="text-gray-900"><?php echo date('d/m/Y H:i', strtotime($projectInfo['created_at'])); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Liste des matériaux du projet -->
                        <div class="mt-6">
                            <h3 class="text-md font-semibold mb-2">Liste des matériaux</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th
                                                class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                                Désignation</th>
                                            <th
                                                class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                                Qté demandée</th>
                                            <th
                                                class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                                Stock</th>
                                            <th
                                                class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                                Achat</th>
                                            <th
                                                class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                                Prix unitaire</th>
                                            <th
                                                class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                                Fournisseur</th>
                                            <th
                                                class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                                Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if (empty($projectMaterials)):
                                            ?>
                                            <tr>
                                                <td colspan="7" class="py-4 px-4 text-gray-500 text-center">Aucun matériau
                                                    enregistré pour ce projet</td>
                                            </tr>
                                            <?php
                                        else:
                                            foreach ($projectMaterials as $material):
                                                // Déterminer la classe du statut
                                                $statusClass = '';
                                                $statusText = '';

                                                if ($material['status'] == 'validé') {
                                                    $statusClass = 'bg-blue-100 text-blue-800';
                                                    $statusText = 'Commandé';
                                                } elseif ($material['status'] == 'reçu') {
                                                    $statusClass = 'bg-green-100 text-green-800';
                                                    $statusText = 'Reçu';
                                                } else {
                                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                                    $statusText = 'En attente';
                                                }
                                                ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="py-2 px-4 border-b">
                                                        <?php echo htmlspecialchars($material['designation']); ?>
                                                    </td>
                                                    <td class="py-2 px-4 border-b">
                                                        <?php echo htmlspecialchars($material['quantity']); ?>
                                                        <?php echo htmlspecialchars($material['unit'] ?? ''); ?>
                                                    </td>
                                                    <td class="py-2 px-4 border-b">
                                                        <?php echo htmlspecialchars($material['qt_stock'] ?? '0'); ?>
                                                    </td>
                                                    <td class="py-2 px-4 border-b">
                                                        <?php echo htmlspecialchars($material['qt_acheter'] ?? '0'); ?>
                                                    </td>
                                                    <td class="py-2 px-4 border-b">
                                                        <?php echo !empty($material['prix_unitaire']) ? formatNumber($material['prix_unitaire']) . ' FCFA' : '-'; ?>
                                                    </td>
                                                    <td class="py-2 px-4 border-b">
                                                        <?php echo htmlspecialchars($material['fournisseur'] ?? '-'); ?>
                                                    </td>
                                                    <td class="py-2 px-4 border-b">
                                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $statusClass; ?>">
                                                            <?php echo $statusText; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php
                                            endforeach;
                                        endif;
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Graphiques -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- Graphique 1: Statut des achats -->
                        <div class="bg-white p-6 shadow-sm rounded-lg">
                            <h2 class="text-lg font-semibold mb-4">Répartition des achats par statut</h2>
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>

                        <!-- Graphique 2: Répartition par catégorie -->
                        <div class="bg-white p-6 shadow-sm rounded-lg">
                            <h2 class="text-lg font-semibold mb-4">Répartition des achats par catégorie</h2>
                            <div class="chart-container">
                                <canvas id="categoriesChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Projets récents -->
                    <div class="bg-white p-6 shadow-sm rounded-lg mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold">Projets récents</h2>
                            <?php if ($selectedClient != 'all'): ?>
                                <span
                                    class="bg-blue-100 text-blue-800 text-xs font-medium rounded-full px-2.5 py-1 flex items-center">
                                    <span class="material-icons text-sm mr-1">business</span>
                                    Client: <?php echo htmlspecialchars($selectedClient); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                            Code Projet</th>
                                        <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                            Client</th>
                                        <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                            Description</th>
                                        <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                            Chef de projet</th>
                                        <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                            Date création</th>
                                        <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                            Articles</th>
                                        <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                            Montant</th>
                                        <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                            Progression</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (empty($recentProjects)):
                                        ?>
                                        <tr>
                                            <td colspan="8" class="py-4 px-4 text-gray-500 text-center">Aucun projet trouvé</td>
                                        </tr>
                                        <?php
                                    else:
                                        foreach ($recentProjects as $project):
                                            // Calculer le pourcentage de progression
                                            $progressPercentage = 0;
                                            if ($project['total_items'] > 0) {
                                                $progressPercentage = round(($project['completed_items'] / $project['total_items']) * 100);
                                            }

                                            // Déterminer la couleur de la barre de progression
                                            $progressColorClass = 'bg-blue-600';
                                            if ($progressPercentage >= 100) {
                                                $progressColorClass = 'bg-green-600';
                                            } elseif ($progressPercentage >= 50) {
                                                $progressColorClass = 'bg-blue-600';
                                            } elseif ($progressPercentage >= 25) {
                                                $progressColorClass = 'bg-yellow-600';
                                            } else {
                                                $progressColorClass = 'bg-red-600';
                                            }

                                            // Formater la date
                                            $createdDate = date('d/m/Y', strtotime($project['created_at']));
                                            ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="py-2 px-4 border-b">
                                                    <a href="projet_details.php?code_projet=<?php echo urlencode($project['code_projet']); ?>"
                                                        class="text-blue-600 hover:text-blue-800 font-medium">
                                                        <?php echo htmlspecialchars($project['code_projet']); ?>
                                                    </a>
                                                </td>
                                                <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($project['nom_client']); ?>
                                                </td>
                                                <td class="py-2 px-4 border-b">
                                                    <?php echo htmlspecialchars(substr($project['description_projet'], 0, 50)) . (strlen($project['description_projet']) > 50 ? '...' : ''); ?>
                                                </td>
                                                <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($project['chefprojet']); ?>
                                                </td>
                                                <td class="py-2 px-4 border-b"><?php echo $createdDate; ?></td>
                                                <td class="py-2 px-4 border-b"><?php echo formatNumber($project['total_items']); ?></td>
                                                <td class="py-2 px-4 border-b font-medium">
                                                    <?php echo formatNumber($project['total_amount']); ?> FCFA
                                                </td>
                                                <td class="py-2 px-4 border-b">
                                                    <div class="flex items-center">
                                                        <div class="w-full bg-gray-200 rounded-full h-2.5 mr-2">
                                                            <div class="<?php echo $progressColorClass; ?> h-2.5 rounded-full"
                                                                style="width: <?php echo $progressPercentage; ?>%"></div>
                                                        </div>
                                                        <span class="text-sm font-medium"><?php echo $progressPercentage; ?>%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Statistiques par catégorie -->
                <div class="bg-white p-6 shadow-sm rounded-lg mb-6">
                    <h2 class="text-lg font-semibold mb-4">Répartition des achats par catégorie de produits</h2>

                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                        Catégorie</th>
                                    <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                        Articles</th>
                                    <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">
                                        Montant</th>
                                    <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase">%
                                        du total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (empty($categoriesStats)):
                                    ?>
                                    <tr>
                                        <td colspan="4" class="py-4 px-4 text-gray-500 text-center">Aucune donnée disponible
                                        </td>
                                    </tr>
                                    <?php
                                else:
                                    foreach ($categoriesStats as $index => $cat):
                                        $categoryName = $cat['category'] ?? 'Non catégorisé';
                                        $percentage = $statsProjects['total_amount'] > 0 ? round(($cat['amount'] / $statsProjects['total_amount']) * 100, 1) : 0;

                                        // Déterminer la couleur du badge
                                        $badgeClass = 'bg-gray-100 text-gray-800'; // Par défaut
                            
                                        switch ($categoryName) {
                                            case 'REVETEMENT DE PEINTURE ET DE PROTECTION':
                                            case 'REPP':
                                                $badgeClass = 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'ELECTRICITE':
                                            case 'ELEC':
                                                $badgeClass = 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'REVETEMENT DE PROTECTION DE SOL':
                                            case 'REPS':
                                                $badgeClass = 'bg-green-100 text-green-800';
                                                break;
                                            case 'ACCESOIRE':
                                            case 'ACC':
                                                $badgeClass = 'bg-purple-100 text-purple-800';
                                                break;
                                            case 'MATERIELS FERREUX':
                                            case 'MAFE':
                                                $badgeClass = 'bg-red-100 text-red-800';
                                                break;
                                            case 'DIVERS':
                                            case 'DIV':
                                                $badgeClass = 'bg-indigo-100 text-indigo-800';
                                                break;
                                            case 'EQUIPEMENT DE PROTECTION INDIVIDUEL':
                                            case 'EDPI':
                                                $badgeClass = 'bg-pink-100 text-pink-800';
                                                break;
                                            case 'OUTILS ET ACCESSOIRES DE SOUDURE':
                                            case 'OACS':
                                                $badgeClass = 'bg-orange-100 text-orange-800';
                                                break;
                                            case 'MATERIELS DE PLOMBERIE':
                                            case 'PLOM':
                                                $badgeClass = 'bg-teal-100 text-teal-800';
                                                break;
                                            case 'BOULONS, VIS ET ECROUS':
                                            case 'BOVE':
                                                $badgeClass = 'bg-gray-100 text-gray-800';
                                                break;
                                        }
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-2 px-4 border-b">
                                                <span class="px-2 py-1 text-xs rounded-full <?php echo $badgeClass; ?>">
                                                    <?php echo htmlspecialchars($categoryName); ?>
                                                </span>
                                            </td>
                                            <td class="py-2 px-4 border-b"><?php echo formatNumber($cat['item_count']); ?></td>
                                            <td class="py-2 px-4 border-b font-medium"><?php echo formatNumber($cat['amount']); ?>
                                                FCFA</td>
                                            <td class="py-2 px-4 border-b">
                                                <div class="flex items-center">
                                                    <div class="w-16 bg-gray-200 rounded-full h-2.5 mr-2">
                                                        <div class="bg-blue-600 h-2.5 rounded-full"
                                                            style="width: <?php echo $percentage; ?>%"></div>
                                                    </div>
                                                    <span><?php echo $percentage; ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                    endforeach;
                                endif;
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Navigation rapide -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">

                <a href="index.php"
                    class="bg-gray-600 hover:bg-gray-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">dashboard</span>
                    <h3 class="text-lg font-semibold">Tableau de Bord</h3>
                    <p class="text-sm opacity-80 mt-1">Vue d'ensemble</p>
                </a>

                <a href="stats_achats.php"
                    class="bg-blue-600 hover:bg-blue-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">shopping_cart</span>
                    <h3 class="text-lg font-semibold">Statistiques des Achats</h3>
                    <p class="text-sm opacity-80 mt-1">Analyse détaillée des commandes et achats</p>
                </a>

                <a href="stats_fournisseurs.php"
                    class="bg-purple-600 hover:bg-purple-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">business</span>
                    <h3 class="text-lg font-semibold">Statistiques des Fournisseurs</h3>
                    <p class="text-sm opacity-80 mt-1">Performance et historique des fournisseurs</p>
                </a>

                <a href="stats_produits.php"
                    class="bg-green-600 hover:bg-green-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">inventory</span>
                    <h3 class="text-lg font-semibold">Statistiques des Produits</h3>
                    <p class="text-sm opacity-80 mt-1">Analyse du stock et des mouvements</p>
                </a>

                <a href="stats_canceled_orders.php"
                    class="bg-red-600 hover:bg-red-700 text-white p-6 rounded-lg shadow-sm text-center hover:shadow-md transition-all">
                    <span class="material-icons text-3xl mb-2">cancel</span>
                    <h3 class="text-lg font-semibold">Commandes Annulées</h3>
                    <p class="text-sm opacity-80 mt-1">Analyse des commandes annulées</p>
                </a>
            </div>
        </main>

        <?php include_once '../../components/footer.html'; ?>
    </div>

    <script src="assets/js/chart_functions.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Mise à jour de la date et de l'heure
            function updateDateTime() {
                const now = new Date();
                const options = {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                };
                const dateTimeStr = now.toLocaleDateString('fr-FR', options);
                document.getElementById('date-time-display').textContent = dateTimeStr;
            }

            updateDateTime();
            setInterval(updateDateTime, 60000);

            // Créer le graphique de statut des achats
            const statusData = <?php echo json_encode($statusChartData ?? []); ?>;

            if (document.getElementById('statusChart') && statusData.labels.length > 0) {
                new Chart(document.getElementById('statusChart'), {
                    type: 'doughnut',
                    data: {
                        labels: statusData.labels,
                        datasets: [{
                            data: statusData.data,
                            backgroundColor: statusData.backgroundColor,
                            borderColor: 'white',
                            borderWidth: 2,
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    boxWidth: 12,
                                    padding: 15
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const label = context.label || '';
                                        const value = context.raw;
                                        const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                        const percentage = Math.round((value / total) * 100);
                                        return `${label}: ${formatMoney(value)} FCFA (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        cutout: '60%'
                    }
                });
            }

            // Créer le graphique des catégories
            const categoriesData = <?php echo json_encode($categoriesChartData ?? []); ?>;

            if (document.getElementById('categoriesChart') && categoriesData.labels.length > 0) {
                new Chart(document.getElementById('categoriesChart'), {
                    type: 'doughnut',
                    data: {
                        labels: categoriesData.labels,
                        datasets: [{
                            data: categoriesData.data,
                            backgroundColor: categoriesData.backgroundColor,
                            borderColor: 'white',
                            borderWidth: 2,
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    boxWidth: 12,
                                    padding: 15
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const label = context.label || '';
                                        const value = context.raw;
                                        const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                        const percentage = Math.round((value / total) * 100);
                                        return `${label}: ${formatMoney(value)} FCFA (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        cutout: '60%'
                    }
                });
            }

            // Fonction pour formater les montants en FCFA
            function formatMoney(amount) {
                return new Intl.NumberFormat('fr-FR', {
                    style: 'decimal',
                    maximumFractionDigits: 0
                }).format(amount);
            }

            // Export PDF
            document.getElementById('export-pdf').addEventListener('click', function () {
                Swal.fire({
                    title: 'Génération du rapport',
                    text: 'Le rapport PDF est en cours de génération...',
                    icon: 'info',
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Construire l'URL avec les paramètres actuels
                let pdfUrl = 'generate_report.php?type=projets';

                // Ajouter les filtres sélectionnés
                const selectedClient = <?php echo json_encode($selectedClient); ?>;
                const selectedCodeProjet = <?php echo json_encode($selectedCodeProjet); ?>;

                if (selectedClient !== 'all') {
                    pdfUrl += '&client=' + encodeURIComponent(selectedClient);

                    if (selectedCodeProjet) {
                        pdfUrl += '&code_projet=' + encodeURIComponent(selectedCodeProjet);
                    }
                }

                // Rediriger vers le script de génération de PDF
                setTimeout(() => {
                    window.location.href = pdfUrl;
                }, 1500);
            });
        });
    </script>
</body>

</html>