<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../../index.php");
    exit();
}

// Vérifier si l'ID est présent
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID manquant']);
    exit();
}

$orderId = intval($_GET['id']);

// Connexion à la base de données
include_once '../../../database/connection.php';

try {
    // Récupérer les informations du bon de commande
    $query = "SELECT file_path, order_number FROM purchase_orders WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Bon de commande non trouvé");
    }

    // Obtenir le chemin du dossier parent (gestion-bon-commande)
    $basePath = dirname(dirname(__FILE__)); // Remonte de 2 niveaux depuis ce fichier
    $purchaseOrdersDir = $basePath . DIRECTORY_SEPARATOR . 'purchase_orders';
    
    // Le chemin dans la DB peut être de plusieurs formats :
    // 1. "purchase_orders/BC_BC-xxx.pdf" (chemin relatif avec répertoire)
    // 2. "BC_BC-xxx.pdf" (juste le nom du fichier)
    // 3. "BC-xxx.pdf" (nom sans le préfixe BC_)
    
    $dbPath = $order['file_path'];
    
    // Construire différentes variantes possibles du chemin
    $possiblePaths = [];
    
    // 1. Utiliser directement le chemin de la DB s'il contient déjà "purchase_orders/"
    if (strpos($dbPath, 'purchase_orders/') === 0) {
        $filename = str_replace('purchase_orders/', '', $dbPath);
        $possiblePaths[] = $purchaseOrdersDir . DIRECTORY_SEPARATOR . $filename;
    } else {
        // Le chemin ne contient que le nom du fichier
        $filename = basename($dbPath);
        $possiblePaths[] = $purchaseOrdersDir . DIRECTORY_SEPARATOR . $filename;
    }
    
    // 2. Ajouter le préfixe BC_ si nécessaire
    if (strpos($filename, 'BC_') !== 0) {
        $possiblePaths[] = $purchaseOrdersDir . DIRECTORY_SEPARATOR . 'BC_' . $filename;
    }
    
    // 3. Si on a un double préfixe BC_BC_, essayer avec un seul
    if (strpos($filename, 'BC_BC_') === 0) {
        $cleanFilename = str_replace('BC_BC_', 'BC_', $filename);
        $possiblePaths[] = $purchaseOrdersDir . DIRECTORY_SEPARATOR . $cleanFilename;
    }
    
    // 4. Chercher par numéro de commande si aucun chemin ne fonctionne
    $possiblePaths[] = 'SEARCH_BY_ORDER_NUMBER';
    
    // Essayer chaque chemin possible
    $filePath = null;
    $found = false;
    
    foreach ($possiblePaths as $path) {
        if ($path === 'SEARCH_BY_ORDER_NUMBER') {
            // Recherche par numéro de commande dans le répertoire
            if (is_dir($purchaseOrdersDir)) {
                $files = scandir($purchaseOrdersDir);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && strpos($file, $order['order_number']) !== false) {
                        $filePath = $purchaseOrdersDir . DIRECTORY_SEPARATOR . $file;
                        if (file_exists($filePath) && is_file($filePath)) {
                            $found = true;
                            break;
                        }
                    }
                }
            }
        } else {
            // Normaliser le chemin pour Windows/Unix
            $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            if (file_exists($normalizedPath) && is_file($normalizedPath)) {
                $filePath = $normalizedPath;
                $found = true;
                break;
            }
        }
    }
    
    if (!$found || !$filePath) {
        // Construire un message d'erreur détaillé pour le débogage
        $debugInfo = "Fichier non trouvé.\n\n";
        $debugInfo .= "Informations de débogage:\n";
        $debugInfo .= "- Chemin dans la DB : " . $dbPath . "\n";
        $debugInfo .= "- Numéro de commande : " . $order['order_number'] . "\n";
        $debugInfo .= "\nChemins tentés:\n";
        foreach ($possiblePaths as $i => $path) {
            if ($path !== 'SEARCH_BY_ORDER_NUMBER') {
                $debugInfo .= ($i + 1) . ". " . $path . " (Existe: " . (file_exists($path) ? "OUI" : "NON") . ")\n";
            }
        }
        
        // Lister les fichiers dans le répertoire (pour le débogage)
        if (is_dir($purchaseOrdersDir)) {
            $files = array_slice(scandir($purchaseOrdersDir), 2, 10); // Les 10 premiers fichiers
            $debugInfo .= "\nPremiers fichiers dans le répertoire:\n";
            foreach ($files as $file) {
                $debugInfo .= "- " . $file . "\n";
            }
        }
        
        throw new Exception($debugInfo);
    }

    // Nettoyer la sortie précédente
    if (ob_get_level()) ob_end_clean();

    // Définir les en-têtes pour le téléchargement
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Bon_Commande_' . $order['order_number'] . '.pdf"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    
    // Lire et envoyer le fichier
    readfile($filePath);
    exit;

} catch (Exception $e) {
    // En cas d'erreur, afficher un message d'erreur formaté
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Erreur de téléchargement</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .error-container { border: 1px solid #f5c6cb; padding: 20px; background-color: #f8d7da; border-radius: 5px; }
            h1 { color: #721c24; }
            p { margin-bottom: 15px; white-space: pre-line; }
            .back-link { display: inline-block; margin-top: 20px; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; }
            .back-link:hover { background-color: #0056b3; }
            .debug-info { background-color: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; white-space: pre; overflow-x: auto; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>Erreur lors du téléchargement</h1>
            <div class="debug-info">' . htmlspecialchars($e->getMessage()) . '</div>
            <a href="../commandes_archive.php" class="back-link">Retour aux archives</a>
        </div>
    </body>
    </html>';
    exit;
}
?>