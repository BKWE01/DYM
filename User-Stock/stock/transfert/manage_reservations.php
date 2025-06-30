<?php
// Enregistrer dans le fichier manage_reservations.php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Connexion à la base de données
include_once '../../../database/connection.php';

// Initialiser les quantités réservées si demandé
if (isset($_POST['initialize_reservations'])) {
    try {
        $pdo->exec("CALL initialize_reserved_quantities()");
        $successMessage = "Initialisation des quantités réservées réussie.";
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de l'initialisation: " . $e->getMessage();
    }
}

// Si une mise à jour directe est demandée
if (isset($_POST['update_reservation'])) {
    try {
        $id = $_POST['id'];
        $quantity = floatval($_POST['quantity_reserved']);
        
        $updateQuery = "UPDATE expression_dym SET quantity_reserved = :quantity WHERE id = :id";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->execute([
            'quantity' => $quantity,
            'id' => $id
        ]);
        
        $successMessage = "Quantité réservée mise à jour avec succès.";
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la mise à jour: " . $e->getMessage();
    }
}

// Récupérer les projets avec des réservations
try {
    $projectsQuery = "
        SELECT DISTINCT ip.id, ip.idExpression, ip.code_projet, ip.nom_client
        FROM identification_projet ip
        JOIN expression_dym ed ON ip.idExpression = ed.idExpression
        WHERE ed.quantity_reserved > 0
        ORDER BY ip.created_at DESC
    ";
    $projectsStmt = $pdo->query($projectsQuery);
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = "Erreur lors de la récupération des projets: " . $e->getMessage();
    $projects = [];
}

// Récupérer les réservations pour un projet spécifique
$selectedProjectId = $_GET['project_id'] ?? null;
$reservations = [];

if ($selectedProjectId) {
    try {
        $reservationsQuery = "
            SELECT ed.*, p.product_name, p.barcode, p.id as product_id
            FROM expression_dym ed
            JOIN identification_projet ip ON ed.idExpression = ip.idExpression
            LEFT JOIN products p ON LOWER(TRIM(ed.designation)) = LOWER(TRIM(p.product_name))
            WHERE ip.id = :project_id
            AND ed.quantity_reserved > 0
            ORDER BY ed.designation
        ";
        $reservationsStmt = $pdo->prepare($reservationsQuery);
        $reservationsStmt->execute(['project_id' => $selectedProjectId]);
        $reservations = $reservationsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Récupérer les informations du projet
        $projectQuery = "SELECT * FROM identification_projet WHERE id = :project_id";
        $projectStmt = $pdo->prepare($projectQuery);
        $projectStmt->execute(['project_id' => $selectedProjectId]);
        $selectedProject = $projectStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des réservations: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionnaire des Quantités Réservées - DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            color: #fff;
            white-space: nowrap;
            border-radius: 9999px;
        }
        .badge-blue { background-color: #3b82f6; }
        .badge-green { background-color: #10b981; }
        .badge-red { background-color: #ef4444; }
        .badge-orange { background-color: #f59e0b; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex flex-col min-h-screen">
        <!-- Sidebar -->
        <?php include_once '../sidebar.php'; ?>

        <!-- Main content -->
        <div id="main-content" class="flex-1 p-6">
            <?php include_once '../header.php'; ?>

            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-semibold">Gestionnaire des Quantités Réservées</h1>
                    
                    <form method="POST" action="" class="flex items-center">
                        <button type="submit" name="initialize_reservations" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-md transition-colors duration-300 flex items-center">
                            <span class="material-icons mr-2">autorenew</span>
                            Réinitialiser les quantités réservées
                        </button>
                    </form>
                </div>

                <?php if (isset($successMessage)): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                        <p><?php echo $successMessage; ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errorMessage)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                        <p><?php echo $errorMessage; ?></p>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <!-- Liste des projets -->
                    <div class="md:col-span-1 bg-gray-50 p-4 rounded-lg">
                        <h2 class="text-lg font-medium text-gray-700 mb-4">Projets avec réservations</h2>
                        
                        <?php if (empty($projects)): ?>
                            <p class="text-gray-500 italic">Aucun projet avec des réservations</p>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($projects as $project): ?>
                                    <a href="?project_id=<?php echo $project['id']; ?>" 
                                       class="block p-3 border rounded-md <?php echo ($selectedProjectId == $project['id']) ? 'bg-blue-50 border-blue-200' : 'bg-white hover:bg-gray-50'; ?>">
                                        <div class="font-medium text-blue-600"><?php echo $project['code_projet']; ?></div>
                                        <div class="text-sm text-gray-600"><?php echo $project['nom_client']; ?></div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Détails des réservations -->
                    <div class="md:col-span-3">
                        <?php if ($selectedProjectId && isset($selectedProject)): ?>
                            <div class="bg-blue-50 p-4 rounded-lg mb-4">
                                <h2 class="text-xl font-semibold text-blue-800"><?php echo $selectedProject['code_projet']; ?></h2>
                                <p class="text-blue-600"><?php echo $selectedProject['nom_client']; ?></p>
                            </div>
                            
                            <?php if (empty($reservations)): ?>
                                <div class="text-center py-8">
                                    <span class="material-icons text-gray-400 text-5xl mb-2">inventory</span>
                                    <p class="text-gray-500">Aucune réservation trouvée pour ce projet.</p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité réservée</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <?php foreach ($reservations as $reservation): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($reservation['designation']); ?></div>
                                                        <?php if ($reservation['product_id']): ?>
                                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($reservation['barcode'] ?? 'N/A'); ?></div>
                                                        <?php else: ?>
                                                            <div class="text-xs text-red-500">Produit non trouvé dans la base de données</div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <form method="POST" action="" class="flex items-center space-x-2">
                                                            <input type="hidden" name="id" value="<?php echo $reservation['id']; ?>">
                                                            <input type="number" name="quantity_reserved" value="<?php echo $reservation['quantity_reserved']; ?>" 
                                                                   class="w-20 px-2 py-1 border rounded-md" min="0" step="0.01">
                                                            <button type="submit" name="update_reservation" class="text-blue-600 hover:text-blue-800">
                                                                <span class="material-icons">check</span>
                                                            </button>
                                                        </form>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                        $status = $reservation['valide_achat'];
                                                        switch ($status) {
                                                            case 'validé':
                                                                echo '<span class="badge badge-green">Commandé</span>';
                                                                break;
                                                            case 'reçu':
                                                                echo '<span class="badge badge-blue">Reçu</span>';
                                                                break;
                                                            case 'en_cours':
                                                                echo '<span class="badge badge-orange">En cours</span>';
                                                                break;
                                                            default:
                                                                echo '<span class="badge badge-red">Non validé</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <button onclick="viewProductDetails('<?php echo $reservation['product_id']; ?>', '<?php echo $selectedProjectId; ?>')"
                                                                class="text-blue-600 hover:text-blue-800 mr-3">
                                                            <span class="material-icons">visibility</span>
                                                        </button>
                                                        
                                                        <button onclick="showTransferHistory('<?php echo $reservation['product_id']; ?>', '<?php echo $selectedProjectId; ?>')"
                                                                class="text-green-600 hover:text-green-800">
                                                            <span class="material-icons">swap_horiz</span>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-12 bg-gray-50 rounded-lg">
                                <span class="material-icons text-gray-400 text-6xl mb-4">inventory_2</span>
                                <h3 class="text-xl font-medium text-gray-700 mb-2">Sélectionnez un projet</h3>
                                <p class="text-gray-500">Choisissez un projet dans la liste pour afficher ses réservations.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour visualiser les détails d'un produit et ses réservations
        function viewProductDetails(productId, projectId) {
            // Rediriger vers l'outil de débogage avec les paramètres appropriés
            window.open(`check_reserved_quantities.php?product_id=${productId}&project_id=${projectId}`, '_blank');
        }
        
        // Fonction pour afficher l'historique des transferts
        function showTransferHistory(productId, projectId) {
            fetch(`get_transfer_history.php?product_id=${productId}&project_id=${projectId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.transfers.length > 0) {
                            // Construire la table HTML pour les transferts
                            let transfersHtml = '<table class="min-w-full bg-white border border-gray-200">';
                            transfersHtml += `<thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 border-b">Date</th>
                                    <th class="px-4 py-2 border-b">Quantité</th>
                                    <th class="px-4 py-2 border-b">Source</th>
                                    <th class="px-4 py-2 border-b">Destination</th>
                                    <th class="px-4 py-2 border-b">Statut</th>
                                </tr>
                            </thead><tbody>`;
                            
                            data.transfers.forEach(transfer => {
                                const date = new Date(transfer.created_at).toLocaleString();
                                let statusBadge = '';
                                
                                switch(transfer.status) {
                                    case 'pending':
                                        statusBadge = '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">En attente</span>';
                                        break;
                                    case 'completed':
                                        statusBadge = '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Complété</span>';
                                        break;
                                    case 'canceled':
                                        statusBadge = '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Annulé</span>';
                                        break;
                                }
                                
                                transfersHtml += `<tr>
                                    <td class="px-4 py-2 border-b">${date}</td>
                                    <td class="px-4 py-2 border-b">${transfer.quantity}</td>
                                    <td class="px-4 py-2 border-b">${transfer.source_project}</td>
                                    <td class="px-4 py-2 border-b">${transfer.destination_project}</td>
                                    <td class="px-4 py-2 border-b">${statusBadge}</td>
                                </tr>`;
                            });
                            
                            transfersHtml += '</tbody></table>';
                            
                            Swal.fire({
                                title: 'Historique des transferts',
                                html: transfersHtml,
                                width: 800,
                                confirmButtonText: 'Fermer'
                            });
                        } else {
                            Swal.fire({
                                title: 'Aucun transfert',
                                text: 'Aucun transfert trouvé pour ce produit et ce projet.',
                                icon: 'info'
                            });
                        }
                    } else {
                        Swal.fire({
                            title: 'Erreur',
                            text: data.message || 'Une erreur est survenue lors de la récupération de l\'historique des transferts.',
                            icon: 'error'
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    Swal.fire({
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors de la communication avec le serveur.',
                        icon: 'error'
                    });
                });
        }
    </script>
</body>
</html>