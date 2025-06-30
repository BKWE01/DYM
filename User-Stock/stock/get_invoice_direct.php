<?php
// /User-Stock/stock/get_invoice_direct.php

header('Content-Type: application/json');

// Connexion à la base de données
include '../../database/connection.php';

// Récupérer l'ID de la facture
$invoiceId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$forceSearch = isset($_GET['force']) && $_GET['force'] == '1';

if ($invoiceId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de facture invalide'
    ]);
    exit;
}

try {
    // Récupérer les informations de la facture
    $query = "SELECT id, invoice_number, file_path, original_filename FROM invoices WHERE id = :invoice_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':invoice_id' => $invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode([
            'success' => false,
            'message' => 'Facture non trouvée'
        ]);
        exit;
    }

    // Extraire le chemin de fichier
    $filePath = $invoice['file_path'];
    $fileName = basename($filePath);

    /**
     * Fonction pour rechercher un fichier dans différents répertoires possibles
     * 
     * @param string $fileName Nom du fichier à chercher
     * @param string $originalPath Chemin original du fichier (peut être incomplet)
     * @return array Informations sur le fichier trouvé ou non
     */
    function findInvoiceFile($fileName, $originalPath, $invoiceId)
    {
        // Déterminer le chemin racine de l'application
        $documentRoot = $_SERVER['DOCUMENT_ROOT'];
        $appRoot = '/expressions_besoins'; // Chemin par défaut

        // Tentative de détection automatique du chemin de l'application
        $scriptPath = $_SERVER['SCRIPT_FILENAME'];
        $rootPosition = strpos($scriptPath, '/expressions_besoins');
        if ($rootPosition !== false) {
            $serverRoot = substr($scriptPath, 0, $rootPosition);
            if ($serverRoot !== $documentRoot) {
                $appRoot = substr($serverRoot, strlen($documentRoot)) . '/expressions_besoins';
            }
        }

        // Si le chemin commence par "../../", extraire la partie après
        if (strpos($originalPath, '../../') === 0) {
            $originalPath = substr($originalPath, 6);
        }

        // Répertoires à vérifier
        $directories = [
            $appRoot . '/uploads/invoices/',
            $appRoot . '/uploads/',
            '/uploads/invoices/',
            '/uploads/',
            '../uploads/invoices/',
            '../../uploads/invoices/'
        ];

        // Formats de date possibles
        $dateDirs = [
            '',                  // Pas de sous-dossier
            date('Y/m/'),        // Format année/mois/
            date('Y/'),          // Format année
            date('Y-m/'),        // Format année-mois
            date('Y-m-d/')       // Format année-mois-jour
        ];

        // 1. Vérifier si le chemin original est une URL valide
        if (filter_var($originalPath, FILTER_VALIDATE_URL)) {
            return [
                'success' => true,
                'file_url' => $originalPath,
                'file_path' => $originalPath,
                'message' => 'URL directe'
            ];
        }

        // 2. Vérifier si le chemin original fonctionne directement
        $fullPath = $documentRoot . '/' . ltrim($originalPath, '/');
        if (file_exists($fullPath)) {
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            return [
                'success' => true,
                'file_url' => $baseUrl . '/' . ltrim($originalPath, '/'),
                'file_path' => $originalPath,
                'message' => 'Chemin original valide'
            ];
        }

        // 3. Essayer le chemin original avec le préfixe de l'application
        $fullPathWithPrefix = $documentRoot . $appRoot . '/' . ltrim($originalPath, '/');
        if (file_exists($fullPathWithPrefix)) {
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            $adjustedPath = $appRoot . '/' . ltrim($originalPath, '/');
            return [
                'success' => true,
                'file_url' => $baseUrl . $adjustedPath,
                'file_path' => $adjustedPath,
                'message' => 'Chemin original avec préfixe d\'application'
            ];
        }

        // 4. Rechercher dans tous les répertoires possibles et formats de date
        foreach ($directories as $dir) {
            foreach ($dateDirs as $dateDir) {
                $testPath = $dir . $dateDir . $fileName;
                $fullPathToTest = $documentRoot . '/' . ltrim($testPath, '/');
                if (file_exists($fullPathToTest)) {
                    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

                    // Mettre à jour le chemin dans la base de données
                    try {
                        include '../../database/connection.php';

                        $updateStmt = $pdo->prepare("UPDATE invoices SET file_path = :path WHERE id = :id");
                        $updateStmt->execute([
                            ':path' => $testPath,
                            ':id' => $invoiceId
                        ]);
                    } catch (Exception $e) {
                        // Ignorer les erreurs de mise à jour
                    }

                    return [
                        'success' => true,
                        'file_url' => $baseUrl . '/' . ltrim($testPath, '/'),
                        'file_path' => $testPath,
                        'message' => 'Trouvé dans un des répertoires candidats'
                    ];
                }
            }
        }

        // 5. Si force=1, recherche approfondie dans tous les répertoires possibles
        if ($GLOBALS['forceSearch']) {
            // Extraire le nom sans extension pour une recherche plus flexible
            $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
            $searchResults = [];

            // Fonction récursive pour chercher des fichiers
            function searchFiles($dir, $pattern, &$results, $maxDepth = 3, $currentDepth = 0)
            {
                if ($currentDepth > $maxDepth)
                    return;

                if (!is_dir($dir))
                    return;

                $files = @scandir($dir);
                if ($files === false)
                    return;

                foreach ($files as $file) {
                    if ($file === '.' || $file === '..')
                        continue;

                    $path = $dir . '/' . $file;

                    if (is_file($path)) {
                        $fileNameWithoutExt = pathinfo($file, PATHINFO_FILENAME);
                        if (stripos($fileNameWithoutExt, $pattern) !== false) {
                            $results[] = $path;
                        }
                    } elseif (is_dir($path)) {
                        searchFiles($path, $pattern, $results, $maxDepth, $currentDepth + 1);
                    }
                }
            }

            // Répertoires à scanner en profondeur
            $searchDirs = [
                $documentRoot . $appRoot . '/uploads',
                $documentRoot . '/uploads',
            ];

            foreach ($searchDirs as $searchDir) {
                if (is_dir($searchDir)) {
                    searchFiles($searchDir, $nameWithoutExt, $searchResults);
                }
            }

            // Si des fichiers sont trouvés
            if (!empty($searchResults)) {
                // Trier par pertinence (similarité des noms)
                usort($searchResults, function ($a, $b) use ($nameWithoutExt) {
                    $scoreA = similar_text($nameWithoutExt, pathinfo(basename($a), PATHINFO_FILENAME));
                    $scoreB = similar_text($nameWithoutExt, pathinfo(basename($b), PATHINFO_FILENAME));
                    return $scoreB - $scoreA; // Ordre décroissant
                });

                // Utiliser le meilleur match
                $bestMatch = $searchResults[0];
                $relativePath = str_replace($documentRoot, '', $bestMatch);
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

                // Mettre à jour le chemin dans la base de données
                try {
                    include '../../database/connection.php';

                    $updateStmt = $pdo->prepare("UPDATE invoices SET file_path = :path WHERE id = :id");
                    $updateStmt->execute([
                        ':path' => $relativePath,
                        ':id' => $invoiceId
                    ]);
                } catch (Exception $e) {
                    // Ignorer les erreurs de mise à jour
                }

                return [
                    'success' => true,
                    'file_url' => $baseUrl . $relativePath,
                    'file_path' => $relativePath,
                    'message' => 'Trouvé par recherche approfondie',
                    'total_matches' => count($searchResults),
                    'alternative_matches' => count($searchResults) > 1 ? array_slice($searchResults, 1, 3) : []
                ];
            }
        }

        // Aucun fichier trouvé
        return [
            'success' => false,
            'message' => 'Fichier non trouvé après recherche exhaustive'
        ];
    }

    // Rechercher le fichier
    $fileInfo = findInvoiceFile($fileName, $filePath, $invoiceId);

    // Renvoyer le résultat
    if ($fileInfo['success']) {
        echo json_encode([
            'success' => true,
            'invoice_id' => $invoice['id'],
            'invoice_number' => $invoice['invoice_number'],
            'original_filename' => $invoice['original_filename'],
            'file_url' => $fileInfo['file_url'],
            'file_path' => $fileInfo['file_path'],
            'method' => $fileInfo['message'],
            'alternative_matches' => isset($fileInfo['alternative_matches']) ? $fileInfo['alternative_matches'] : []
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $fileInfo['message'],
            'invoice_id' => $invoice['id'],
            'original_path' => $filePath,
            'file_name' => $fileName,
            'searched_directories' => $fileInfo['searched_directories'] ?? []
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}