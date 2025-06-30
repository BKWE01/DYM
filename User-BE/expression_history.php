<?php
// Fichier: /DYM MANUFACTURE/expressions_besoins/User-BE/expression_history.php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../index.php");
    exit();
}

// Connexion à la base de données
include_once '../database/connection.php';

// Vérifier si un ID d'expression a été fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$expressionId = $_GET['id'];

try {
    // Récupérer les informations du projet
    $stmt = $pdo->prepare("
        SELECT * FROM identification_projet 
        WHERE idExpression = :id
    ");
    $stmt->bindParam(':id', $expressionId, PDO::PARAM_STR);
    $stmt->execute();
    $projectInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$projectInfo) {
        header("Location: dashboard.php");
        exit();
    }

    // Récupérer l'historique des modifications pour ce projet
    $stmt = $pdo->prepare("
        SELECT 
            sl.id,
            sl.user_id,
            sl.username,
            sl.action,
            sl.details,
            sl.created_at,
            u.name as user_name,
            u.profile_image
        FROM system_logs sl
        LEFT JOIN users_exp u ON sl.user_id = u.id
        WHERE sl.entity_id = :expression_id
        AND sl.type = 'expression'
        ORDER BY sl.created_at DESC
    ");
    $stmt->bindParam(':expression_id', $expressionId, PDO::PARAM_STR);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de base de données: " . $e->getMessage());
}

// Fonction pour formater les détails du log
function formatLogDetails($details)
{
    $data = json_decode($details, true);
    if (!$data)
        return "Détails non disponibles";

    $formattedDetails = "";

    if (isset($data['action'])) {
        switch ($data['action']) {
            case 'update':
                $formattedDetails .= "<strong>Type d'action:</strong> Mise à jour<br>";

                if (isset($data['updated_expressions']) && is_array($data['updated_expressions'])) {
                    $formattedDetails .= "<strong>Modifications:</strong> " . count($data['updated_expressions']) . " produits<br>";
                }

                if (isset($data['deleted_rows']) && is_array($data['deleted_rows'])) {
                    $formattedDetails .= "<strong>Suppressions:</strong> " . count($data['deleted_rows']) . " produits<br>";
                }
                break;

            case 'delete':
                $formattedDetails .= "<strong>Type d'action:</strong> Suppression<br>";

                if (isset($data['projet'])) {
                    $formattedDetails .= "<strong>Projet:</strong> " . htmlspecialchars($data['projet']) . "<br>";
                }

                if (isset($data['client'])) {
                    $formattedDetails .= "<strong>Client:</strong> " . htmlspecialchars($data['client']) . "<br>";
                }

                if (isset($data['produits_supprimés']) && is_array($data['produits_supprimés'])) {
                    $formattedDetails .= "<strong>Produits supprimés:</strong> " . count($data['produits_supprimés']) . "<br>";
                }
                break;

            default:
                $formattedDetails .= "<strong>Type d'action:</strong> " . htmlspecialchars($data['action']) . "<br>";
        }
    }

    return $formattedDetails;
}

// Fonction pour formater la date
function formatDate($dateString)
{
    $date = new DateTime($dateString);
    return $date->format('d/m/Y à H:i:s');
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des modifications | Expression de besoin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: #334155;
        }

        .wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .timeline {
            position: relative;
            margin: 0 auto;
            padding: 20px 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50px;
            width: 2px;
            background-color: #e2e8f0;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 30px;
            padding-left: 80px;
        }

        .timeline-badge {
            position: absolute;
            left: 30px;
            top: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #3b82f6;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
        }

        .timeline-badge.update {
            background-color: #10b981;
            /* Vert */
        }

        .timeline-badge.delete {
            background-color: #ef4444;
            /* Rouge */
        }

        .timeline-content {
            position: relative;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .timeline-date {
            display: block;
            margin-bottom: 10px;
            color: #6b7280;
            font-size: 14px;
        }

        .timeline-user {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .timeline-user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }

        .timeline-user-name {
            font-weight: 600;
            color: #1f2937;
        }

        .timeline-details {
            background-color: #f9fafb;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            line-height: 1.6;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background-color: #e5e7eb;
            color: #4b5563;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
        }

        .back-button:hover {
            background-color: #d1d5db;
        }

        .back-button .material-icons {
            margin-right: 8px;
            font-size: 20px;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once '../components/navbar.php'; ?>

        <main class="flex-1 p-6">
            <div class="bg-white shadow-sm rounded-lg p-4 mb-4 flex justify-between items-center">
                <h1 class="text-xl font-bold">Historique des modifications - Expression
                    <?php echo htmlspecialchars($expressionId); ?></h1>
                <button class="back-button" onclick="window.location.href='dashboard.php'">
                    <span class="material-icons">arrow_back</span>
                    Retour au tableau de bord
                </button>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4">Informations du projet</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Code projet</p>
                        <p class="font-medium"><?php echo htmlspecialchars($projectInfo['code_projet']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Nom du client</p>
                        <p class="font-medium"><?php echo htmlspecialchars($projectInfo['nom_client']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Description du projet</p>
                        <p class="font-medium"><?php echo htmlspecialchars($projectInfo['description_projet']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Chef de projet</p>
                        <p class="font-medium"><?php echo htmlspecialchars($projectInfo['chefprojet']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h2 class="text-lg font-semibold mb-6">Historique des modifications</h2>

                <?php if (empty($logs)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <span class="material-icons text-4xl mb-2">history</span>
                        <p>Aucun historique de modification trouvé pour cette expression.</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($logs as $log):
                            $logData = json_decode($log['details'], true);
                            $badgeClass = ($logData && isset($logData['action'])) ?
                                ($logData['action'] === 'delete' ? 'delete' : 'update') : '';
                            $badgeIcon = ($logData && isset($logData['action'])) ?
                                ($logData['action'] === 'delete' ? 'delete' : 'edit') : 'edit';
                            ?>
                            <div class="timeline-item">
                                <div class="timeline-badge <?php echo htmlspecialchars($badgeClass); ?>">
                                    <span class="material-icons"><?php echo htmlspecialchars($badgeIcon); ?></span>
                                </div>
                                <div class="timeline-content">
                                    <span class="timeline-date"><?php echo formatDate($log['created_at']); ?></span>
                                    <div class="timeline-user">
                                        <?php if (!empty($log['profile_image'])): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($log['profile_image']); ?>"
                                                alt="Photo de profil" class="timeline-user-avatar">
                                        <?php else: ?>
                                            <img src="../uploads/default-profile-image.png" alt="Photo de profil par défaut"
                                                class="timeline-user-avatar">
                                        <?php endif; ?>
                                        <span class="timeline-user-name">
                                            <?php echo htmlspecialchars($log['user_name'] ?? $log['username'] ?? 'Utilisateur inconnu'); ?>
                                        </span>
                                    </div>
                                    <div class="timeline-details">
                                        <?php echo formatLogDetails($log['details']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <?php include_once '../components/footer.html'; ?>
    </div>
</body>

</html>