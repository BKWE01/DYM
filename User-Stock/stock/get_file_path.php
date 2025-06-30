<?php
// 1. Créez un nouveau fichier get_file_path.php qui servira de résolveur intelligent de chemins
header('Content-Type: application/json');

/**
 * Classe FilePathResolver - Résolveur intelligent de chemins de fichiers
 * Cette classe permet de localiser des fichiers même si leur chemin a changé
 */
class FilePathResolver
{
    private $rootDirectories = [];
    private $searchDepth = 3; // Profondeur de recherche maximale
    private $possibleDateFormats = ['Y/m/', 'Y-m/', 'Y/', 'Y-m-d/']; // Formats de date possibles

    /**
     * Constructeur - Configure les répertoires racines à rechercher
     */
    public function __construct()
    {
        // Déterminer le chemin racine de l'application
        $documentRoot = $_SERVER['DOCUMENT_ROOT'];
        $scriptPath = $_SERVER['SCRIPT_FILENAME'];

        // Trouver la position du dossier "expressions_besoins" dans le chemin du script
        $appRoot = '';
        if (strpos($scriptPath, 'expressions_besoins') !== false) {
            $parts = explode('expressions_besoins', $scriptPath);
            if (isset($parts[0])) {
                $appRoot = $parts[0] . 'expressions_besoins';
            }
        }

        // Configurer les répertoires racines à explorer
        $this->rootDirectories = [
            // Répertoires relatifs au dossier racine de l'application
            $appRoot . '/uploads/invoices',
            $appRoot . '/uploads',
            // Remonter d'un niveau
            dirname($appRoot) . '/uploads/invoices',
            dirname($appRoot) . '/uploads',
            // Répertoires absolus
            $documentRoot . '/expressions_besoins/uploads/invoices',
            $documentRoot . '/expressions_besoins/uploads',
            $documentRoot . '/uploads/invoices',
            $documentRoot . '/uploads',
        ];
    }

    /**
     * Recherche un fichier dans tous les répertoires possibles
     * 
     * @param string $filename Le nom du fichier à rechercher
     * @param string $originalPath Le chemin d'origine du fichier (peut être incomplet ou incorrect)
     * @param int $invoiceId ID de la facture pour les logs
     * @return array Résultat de la recherche [success, file_path, url]
     */
    public function findFile($filename, $originalPath = '', $invoiceId = 0)
    {
        $result = [
            'success' => false,
            'file_path' => '',
            'url' => '',
            'message' => '',
            'searched_paths' => []
        ];

        // Extraire le nom du fichier si un chemin complet est fourni
        if (!empty($originalPath) && $filename != basename($originalPath)) {
            $filename = basename($originalPath);
        }

        // 1. Étape 1: Essayer directement avec le chemin original s'il est fourni
        if (!empty($originalPath)) {
            // Nettoyer le chemin original (enlever "../../" si présent)
            if (strpos($originalPath, '../../') === 0) {
                $originalPath = substr($originalPath, 6);
            }

            // Vérifier si le chemin original est une URL complète
            if (filter_var($originalPath, FILTER_VALIDATE_URL)) {
                return [
                    'success' => true,
                    'file_path' => $originalPath,
                    'url' => $originalPath,
                    'message' => 'Fichier trouvé avec URL originale'
                ];
            }

            // Essayer avec le chemin original directement
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($originalPath, '/');
            $result['searched_paths'][] = $fullPath;

            if (file_exists($fullPath) && is_file($fullPath)) {
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                return [
                    'success' => true,
                    'file_path' => $originalPath,
                    'url' => $baseUrl . '/' . ltrim($originalPath, '/'),
                    'message' => 'Fichier trouvé avec le chemin original'
                ];
            }
        }

        // 2. Étape 2: Recherche systématique dans tous les répertoires racines
        foreach ($this->rootDirectories as $rootDir) {
            // Essayer d'abord sans sous-dossier de date
            $path = $rootDir . '/' . $filename;
            $result['searched_paths'][] = $path;

            if (file_exists($path) && is_file($path)) {
                // Convertir en chemin relatif pour l'URL
                $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $path);
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

                return [
                    'success' => true,
                    'file_path' => $relativePath,
                    'url' => $baseUrl . $relativePath,
                    'message' => 'Fichier trouvé dans un répertoire racine'
                ];
            }

            // Essayer avec les formats de date possibles
            foreach ($this->possibleDateFormats as $dateFormat) {
                $dateDir = date($dateFormat);
                $path = $rootDir . '/' . $dateDir . $filename;
                $result['searched_paths'][] = $path;

                if (file_exists($path) && is_file($path)) {
                    // Convertir en chemin relatif pour l'URL
                    $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $path);
                    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

                    return [
                        'success' => true,
                        'file_path' => $relativePath,
                        'url' => $baseUrl . $relativePath,
                        'message' => 'Fichier trouvé avec format de date'
                    ];
                }
            }
        }

        // 3. Étape 3: Recherche plus approfondie (récursive)
        $fileNameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $foundFiles = [];

        foreach ($this->rootDirectories as $rootDir) {
            if (is_dir($rootDir)) {
                $this->searchRecursive($rootDir, $fileNameWithoutExt, $foundFiles, 0);
            }
        }

        if (!empty($foundFiles)) {
            // Trier les résultats par pertinence
            usort($foundFiles, function ($a, $b) use ($fileNameWithoutExt) {
                // Donner la priorité aux fichiers dont le nom correspond exactement
                $scoreA = similar_text($fileNameWithoutExt, pathinfo($a, PATHINFO_FILENAME));
                $scoreB = similar_text($fileNameWithoutExt, pathinfo($b, PATHINFO_FILENAME));
                return $scoreB - $scoreA;
            });

            // Utiliser le meilleur résultat
            $bestMatch = $foundFiles[0];
            $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $bestMatch);
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

            // Mettre à jour le chemin dans la base de données si un ID de facture est fourni
            if ($invoiceId > 0) {
                $this->updateInvoicePath($invoiceId, $relativePath);
            }

            return [
                'success' => true,
                'file_path' => $relativePath,
                'url' => $baseUrl . $relativePath,
                'message' => 'Fichier trouvé par recherche récursive',
                'alternative_matches' => count($foundFiles) > 1 ? array_slice($foundFiles, 1, 3) : []
            ];
        }

        // Aucun fichier trouvé
        $result['message'] = 'Fichier non trouvé après recherche approfondie';
        return $result;
    }

    /**
     * Recherche récursive d'un fichier dans un répertoire
     * 
     * @param string $dir Répertoire à explorer
     * @param string $pattern Motif de recherche (nom de fichier sans extension)
     * @param array &$results Liste des fichiers trouvés (référence)
     * @param int $depth Profondeur actuelle de recherche
     */
    private function searchRecursive($dir, $pattern, &$results, $depth)
    {
        // Limiter la profondeur de recherche
        if ($depth > $this->searchDepth) {
            return;
        }

        // Récupérer la liste des fichiers et dossiers
        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            // Ignorer les entrées spéciales
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            // Traiter les fichiers
            if (is_file($path)) {
                $fileNameWithoutExt = pathinfo($item, PATHINFO_FILENAME);
                // Vérifier si le nom du fichier contient le motif recherché
                if (stripos($fileNameWithoutExt, $pattern) !== false) {
                    $results[] = $path;
                }
            }
            // Explorer les sous-répertoires
            elseif (is_dir($path)) {
                $this->searchRecursive($path, $pattern, $results, $depth + 1);
            }
        }
    }

    /**
     * Met à jour le chemin du fichier dans la base de données
     * 
     * @param int $invoiceId ID de la facture
     * @param string $newPath Nouveau chemin du fichier
     * @return bool Succès de la mise à jour
     */
    private function updateInvoicePath($invoiceId, $newPath)
    {
        // Connexion à la base de données
        include_once '../../database/connection.php'; 
        
        try {
            // Mettre à jour le chemin
            $stmt = $pdo->prepare("UPDATE invoices SET file_path = :file_path WHERE id = :id");
            $result = $stmt->execute([
                ':file_path' => $newPath,
                ':id' => $invoiceId
            ]);

            return $result;
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour du chemin de facture: " . $e->getMessage());
            return false;
        }
    }
}

// Traitement de la requête
$fileResolver = new FilePathResolver();

// Récupérer les paramètres de la requête
$filename = isset($_GET['filename']) ? $_GET['filename'] : '';
$originalPath = isset($_GET['path']) ? $_GET['path'] : '';
$invoiceId = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;

// Vérifier les paramètres requis
if (empty($filename) && empty($originalPath)) {
    echo json_encode([
        'success' => false,
        'message' => 'Veuillez fournir un nom de fichier ou un chemin d\'origine'
    ]);
    exit;
}

// Rechercher le fichier
$result = $fileResolver->findFile($filename, $originalPath, $invoiceId);

// Renvoyer le résultat
echo json_encode($result);
?>