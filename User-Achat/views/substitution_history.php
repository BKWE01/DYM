<?php
/**
 * Page d'historique des substitutions de produits
 * 
 * Cette page permet de visualiser toutes les substitutions de produits effectuées,
 * avec les détails et les raisons de chaque substitution.
 * 
 * @package DYM_MANUFACTURE
 * @subpackage expressions_besoins
 */

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Connexion à la base de données et helpers
include_once '../../database/connection.php';
include_once '../../include/date_helper.php';

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

// Variables pour stocker les données
$substitutions = [];
$message = '';

try {
    // Récupérer l'historique des substitutions avec les noms d'utilisateurs
    $query = "SELECT ps.*, 
    u.name as user_name,
    CASE 
        WHEN ps.source_table = 'besoins' THEN b.designation_article
        ELSE ip.code_projet
    END as code_projet,
    CASE 
        WHEN ps.source_table = 'besoins' THEN d.client
        ELSE ip.nom_client
    END as nom_client
FROM product_substitutions ps
LEFT JOIN users_exp u ON ps.user_id = u.id
LEFT JOIN expression_dym ed ON ps.source_table = 'expression_dym' AND ps.material_id = ed.id
LEFT JOIN identification_projet ip ON ps.source_table = 'expression_dym' AND ps.expression_id = ip.idExpression
LEFT JOIN besoins b ON ps.source_table = 'besoins' AND ps.material_id = b.id
LEFT JOIN demandeur d ON ps.source_table = 'besoins' AND ps.expression_id = d.idBesoin
ORDER BY ps.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $substitutions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $message = "Une erreur s'est produite lors de la récupération de l'historique : " . $e->getMessage();
}

// Fonction pour formater les raisons de substitution
function formatReason($reason, $otherReason)
{
    switch ($reason) {
        case 'indisponibilite':
            return 'Produit indisponible';
        case 'meilleur_prix':
            return 'Meilleur prix';
        case 'qualite_superieure':
            return 'Qualité supérieure';
        case 'autre':
            return 'Autre raison: ' . $otherReason;
        default:
            return $reason;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Substitutions</title>

    <!-- Styles CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">

    <style>
        .wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="wrapper">
        <?php include_once '../../components/navbar_achat.php'; ?>

        <main class="flex-1 p-6">
            <!-- En-tête de la page -->
            <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex flex-wrap justify-between items-center">
                <div class="flex items-center m-2">
                    <h1 class="text-xl font-semibold text-gray-800">Historique des Substitutions de Produits</h1>
                </div>

                <div class="flex items-center m-2">
                    <a href="../achats_materiaux.php"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        <span class="material-icons align-middle mr-1">arrow_back</span>
                        Retour aux achats
                    </a>
                </div>
            </div>

            <!-- Message flash -->
            <?php if (!empty($message)): ?>
                <div id="flash-message"
                    class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <span class="block sm:inline"><?php echo $message; ?></span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3"
                        onclick="document.getElementById('flash-message').style.display='none';">
                        <span class="material-icons">close</span>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Contenu principal - Tableau des substitutions -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-700 mb-4">Liste des substitutions effectuées</h2>

                <div class="overflow-x-auto">
                    <table id="substitutionsTable" class="display responsive nowrap w-full">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Projet</th>
                                <th>Client</th>
                                <th>Produit Original</th>
                                <th>Produit Substitué</th>
                                <th>Quantité</th>
                                <th>Raison</th>
                                <th>Effectué par</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($substitutions as $sub): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($sub['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($sub['code_projet'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($sub['nom_client'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($sub['original_product']); ?>
                                        <span
                                            class="text-xs text-gray-500">(<?php echo htmlspecialchars($sub['original_unit'] ?? 'unité'); ?>)</span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($sub['substitute_product']); ?>
                                        <span
                                            class="text-xs text-gray-500">(<?php echo htmlspecialchars($sub['substitute_unit'] ?? 'unité'); ?>)</span>
                                    </td>
                                    <td><?php echo number_format($sub['quantity_transferred'], 2, ',', ' '); ?></td>
                                    <td><?php echo htmlspecialchars(formatReason($sub['reason'], $sub['other_reason'])); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($sub['user_name'] ?? 'Utilisateur inconnu'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <?php include_once '../../components/footer.html'; ?>
    </div>

    <!-- Scripts jQuery et DataTables -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

    <script>
        $(document).ready(function () {
            $('#substitutionsTable').DataTable({
                responsive: true,
                language: { url: "//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json" },
                dom: 'Blfrtip',
                buttons: ['excel', 'print'],
                order: [[0, 'desc']], // Trier par date décroissante
                pageLength: 15
            });
        });
    </script>
</body>

</html>