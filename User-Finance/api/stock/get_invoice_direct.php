<?php
// /User-Finance/api/stock/get_invoice_direct.php
// API pour récupérer et localiser les factures

// Configuration des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 0); // Désactivé en production

header('Content-Type: application/json; charset=utf-8');

// Connexion à la base de données - CHEMIN CORRIGÉ POUR FINANCE
include_once '../../../database/connection.php';

// Récupérer les paramètres
$invoiceId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$forceSearch = isset($_GET['force']) && $_GET['force'] == '1';

// Validation de l'ID
if ($invoiceId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de facture invalide',
        'error_code' => 'INVALID_ID'
    ]);
    exit;
}

try {
    // ===== RÉCUPÉRATION DES INFORMATIONS DE LA FACTURE =====
    $query = "SELECT 
                id, 
                invoice_number, 
                file_path, 
                original_filename,
                created_at,
                updated_at
              FROM invoices 
              WHERE id = :invoice_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':invoice_id' => $invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode([
            'success' => false,
            'message' => 'Facture non trouvée dans la base de données',
            'error_code' => 'INVOICE_NOT_FOUND',
            'invoice_id' => $invoiceId
        ]);
        exit;
    }

    // ===== LOCALISATION DU FICHIER =====
    $filePath = $invoice['file_path'];
    $fileName = basename($filePath);
    
    // Fonction améliorée de recherche de fichier
    function findInvoiceFile($fileName, $originalPath, $invoiceId, $pdo) {
        // Configuration des chemins
        $documentRoot = $_SERVER['DOCUMENT_ROOT'];
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
        
        // Déterminer le préfixe de l'application
        $appPrefix = '';
        $scriptPath = $_SERVER['SCRIPT_FILENAME'];
        
        if (strpos($scriptPath, 'expressions_besoins') !== false) {
            $appPrefix = '/DYM MANUFACTURE/expressions_besoins';
        }
        
        // Nettoyer le chemin original
        $cleanPath = $originalPath;
        
        // Supprimer les préfixes relatifs
        $cleanPath = preg_replace('/^(\.\.\/)+/', '', $cleanPath);
        $cleanPath = ltrim($cleanPath, '/');
        
        // ===== ÉTAPE 1: VÉRIFIER LE CHEMIN ORIGINAL =====
        $possiblePaths = [];
        
        // Chemin original direct
        $possiblePaths[] = $cleanPath;
        
        // Avec préfixe d'application
        if ($appPrefix) {
            $possiblePaths[] = $appPrefix . '/' . $cleanPath;
        }
        
        // ===== ÉTAPE 2: CHEMINS STANDARDS POUR LES FACTURES =====
        $standardPaths = [
            'uploads/invoices/',
            'uploads/invoices/2025/05/',
            'uploads/invoices/2025/06/',
            'uploads/invoices/2025/',
            'uploads/',
            'User-Stock/uploads/invoices/',
            'User-Finance/uploads/invoices/'
        ];
        
        foreach ($standardPaths as $standardPath) {
            $possiblePaths[] = $standardPath . $fileName;
            
            if ($appPrefix) {
                $possiblePaths[] = $appPrefix . '/' . $standardPath . $fileName;
            }
        }
        
        // ===== ÉTAPE 3: TESTS DE TOUS LES CHEMINS =====
        foreach ($possiblePaths as $testPath) {
            $fullPath = $documentRoot . '/' . ltrim($testPath, '/');
            
            if (file_exists($fullPath) && is_readable($fullPath)) {
                // Fichier trouvé ! Mettre à jour la base de données
                try {
                    $updateStmt = $pdo->prepare("UPDATE invoices SET file_path = :path WHERE id = :id");
                    $updateStmt->execute([
                        ':path' => $testPath,
                        ':id' => $invoiceId
                    ]);
                } catch (Exception $e) {
                    // Ignorer les erreurs de mise à jour
                    error_log("Erreur mise à jour chemin facture: " . $e->getMessage());
                }

                return [
                    'success' => true,
                    'file_url' => $baseUrl . '/' . ltrim($testPath, '/'),
                    'file_path' => $testPath,
                    'full_path' => $fullPath,
                    'method' => 'Chemin standard trouvé',
                    'file_size' => filesize($fullPath),
                    'file_type' => mime_content_type($fullPath)
                ];
            }
        }
        
        // ===== ÉTAPE 4: RECHERCHE RÉCURSIVE (SI FORCE=1) =====
        if ($GLOBALS['forceSearch']) {
            $searchResult = recursiveFileSearch($fileName, $documentRoot, $appPrefix, $pdo, $invoiceId);
            if ($searchResult['success']) {
                return $searchResult;
            }
        }
        
        // ===== AUCUN FICHIER TROUVÉ =====
        return [
            'success' => false,
            'message' => 'Fichier de facture introuvable',
            'error_code' => 'FILE_NOT_FOUND',
            'searched_paths' => $possiblePaths,
            'original_path' => $originalPath,
            'file_name' => $fileName,
            'suggestions' => generateSuggestions($fileName, $documentRoot, $appPrefix)
        ];
    }
    
    // Fonction de recherche récursive
    function recursiveFileSearch($fileName, $documentRoot, $appPrefix, $pdo, $invoiceId) {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
        
        // Répertoires à scanner
        $searchDirs = [
            $documentRoot . $appPrefix . '/uploads',
            $documentRoot . '/uploads',
            $documentRoot . $appPrefix . '/User-Stock/uploads',
            $documentRoot . $appPrefix . '/User-Finance/uploads'
        ];
        
        foreach ($searchDirs as $searchDir) {
            if (is_dir($searchDir)) {
                $result = searchInDirectory($searchDir, $fileName, $documentRoot, $baseUrl, $pdo, $invoiceId);
                if ($result['success']) {
                    return $result;
                }
            }
        }
        
        return ['success' => false, 'message' => 'Recherche récursive sans succès'];
    }
    
    // Fonction de recherche dans un répertoire
    function searchInDirectory($dir, $fileName, $documentRoot, $baseUrl, $pdo, $invoiceId, $maxDepth = 3, $currentDepth = 0) {
        if ($currentDepth > $maxDepth || !is_dir($dir)) {
            return ['success' => false];
        }
        
        $files = @scandir($dir);
        if ($files === false) {
            return ['success' => false];
        }
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filePath = $dir . '/' . $file;
            
            if (is_file($filePath) && $file === $fileName) {
                // Fichier trouvé !
                $relativePath = str_replace($documentRoot . '/', '', $filePath);
                $relativePath = ltrim($relativePath, '/');
                
                // Mettre à jour la base de données
                try {
                    $updateStmt = $pdo->prepare("UPDATE invoices SET file_path = :path WHERE id = :id");
                    $updateStmt->execute([
                        ':path' => $relativePath,
                        ':id' => $invoiceId
                    ]);
                } catch (Exception $e) {
                    error_log("Erreur mise à jour chemin facture: " . $e->getMessage());
                }
                
                return [
                    'success' => true,
                    'file_url' => $baseUrl . '/' . $relativePath,
                    'file_path' => $relativePath,
                    'full_path' => $filePath,
                    'method' => 'Recherche récursive réussie',
                    'file_size' => filesize($filePath),
                    'file_type' => mime_content_type($filePath)
                ];
                
            } elseif (is_dir($filePath)) {
                // Continuer la recherche dans les sous-répertoires
                $result = searchInDirectory($filePath, $fileName, $documentRoot, $baseUrl, $pdo, $invoiceId, $maxDepth, $currentDepth + 1);
                if ($result['success']) {
                    return $result;
                }
            }
        }
        
        return ['success' => false];
    }
    
    // Fonction pour générer des suggestions
    function generateSuggestions($fileName, $documentRoot, $appPrefix) {
        $suggestions = [];
        $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
        
        // Rechercher des fichiers similaires
        $searchDirs = [
            $documentRoot . $appPrefix . '/uploads/invoices',
            $documentRoot . '/uploads/invoices'
        ];
        
        foreach ($searchDirs as $searchDir) {
            if (is_dir($searchDir)) {
                $files = @glob($searchDir . '/*' . $nameWithoutExt . '*', GLOB_BRACE);
                if ($files) {
                    foreach (array_slice($files, 0, 3) as $file) { // Limiter à 3 suggestions
                        $suggestions[] = basename($file);
                    }
                }
            }
        }
        
        return array_unique($suggestions);
    }
    
    // ===== EXÉCUTION DE LA RECHERCHE =====
    $fileInfo = findInvoiceFile($fileName, $filePath, $invoiceId, $pdo);
    
    // ===== RETOUR DE LA RÉPONSE =====
    if ($fileInfo['success']) {
        echo json_encode([
            'success' => true,
            'invoice_id' => $invoice['id'],
            'invoice_number' => $invoice['invoice_number'],
            'original_filename' => $invoice['original_filename'],
            'file_url' => $fileInfo['file_url'],
            'file_path' => $fileInfo['file_path'],
            'file_size' => $fileInfo['file_size'] ?? null,
            'file_type' => $fileInfo['file_type'] ?? null,
            'method' => $fileInfo['method'],
            'force_search_used' => $forceSearch,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $fileInfo['message'],
            'error_code' => $fileInfo['error_code'] ?? 'FILE_SEARCH_FAILED',
            'invoice_id' => $invoice['id'],
            'invoice_number' => $invoice['invoice_number'],
            'original_path' => $filePath,
            'file_name' => $fileName,
            'searched_paths' => $fileInfo['searched_paths'] ?? [],
            'suggestions' => $fileInfo['suggestions'] ?? [],
            'force_search_used' => $forceSearch,
            'debug_info' => [
                'document_root' => $_SERVER['DOCUMENT_ROOT'],
                'script_path' => $_SERVER['SCRIPT_FILENAME'],
                'app_detected' => strpos($_SERVER['SCRIPT_FILENAME'], 'expressions_besoins') !== false
            ]
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage(),
        'error_code' => 'DATABASE_ERROR',
        'error_type' => 'database'
    ]);
    
    error_log("Erreur PDO dans get_invoice_direct.php: " . $e->getMessage());
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur système: ' . $e->getMessage(),
        'error_code' => 'SYSTEM_ERROR',
        'error_type' => 'system'
    ]);
    
    error_log("Erreur générale dans get_invoice_direct.php: " . $e->getMessage());
}
?>