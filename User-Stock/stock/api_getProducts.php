<?php
// Connexion à la base de données
include_once '../../database/connection.php';

try {
    // Récupération des paramètres de recherche, filtrage et limite
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $offset = ($page - 1) * $limit;

    // Paramètres de recherche
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $stockStatus = isset($_GET['stockStatus']) ? $_GET['stockStatus'] : '';
    $sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'id';
    $sortOrder = isset($_GET['sortOrder']) ? (strtoupper($_GET['sortOrder']) === 'ASC' ? 'ASC' : 'DESC') : 'DESC';

    // Optimisation de la recherche avec opérateurs booléens
    $searchTerms = [];
    $searchParams = [];
    $searchIndex = 0;

    if (!empty($search)) {
        // Support pour les codes-barres exacts (recherche avec #)
        if (strpos($search, '#') === 0) {
            $barcode = substr($search, 1);
            $searchTerms[] = "p.barcode = :barcode";
            $searchParams[':barcode'] = $barcode;
        }
        // Recherche par ID si le terme de recherche est numérique uniquement
        else if (is_numeric($search) && strlen($search) < 10) {
            $searchTerms[] = "(p.id = :id OR p.barcode LIKE :barcode_search)";
            $searchParams[':id'] = intval($search);
            $searchParams[':barcode_search'] = "%$search%";
        }
        // Recherche standard
        else {
            // Diviser la recherche en termes pour une recherche plus précise
            $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);

            foreach ($words as $word) {
                $wordParam = ":search" . $searchIndex;
                $searchTerms[] = "(p.product_name LIKE $wordParam OR p.barcode LIKE $wordParam OR p.unit LIKE $wordParam OR c.libelle LIKE $wordParam)";
                $searchParams[$wordParam] = "%$word%";
                $searchIndex++;
            }
        }
    }

    // Construction de la requête SQL de base
    $sql = "SELECT p.*, c.libelle as category_libelle 
            FROM products p 
            LEFT JOIN categories c ON p.category = c.id
            WHERE 1=1";

    // Ajout des conditions de recherche
    if (!empty($searchTerms)) {
        $sql .= " AND (" . implode(" AND ", $searchTerms) . ")";
    }

    // Filtrage par catégorie
    if (!empty($category)) {
        $sql .= " AND p.category = :category";
        $searchParams[':category'] = $category;
    }

    // Filtrage par statut de stock
    if (!empty($stockStatus)) {
        switch ($stockStatus) {
            case 'outOfStock':
                $sql .= " AND p.quantity <= 0";
                break;
            case 'lowStock':
                $sql .= " AND p.quantity > 0 AND p.quantity <= 10";
                break;
            case 'inStock':
                $sql .= " AND p.quantity > 10";
                break;
        }
    }

    // Requête pour compter le nombre total d'enregistrements (pour la pagination)
    $countSql = "SELECT COUNT(*) as total FROM ($sql) as count_table";
    $countStmt = $pdo->prepare($countSql);

    // Liaison des paramètres pour la requête de comptage
    foreach ($searchParams as $key => $value) {
        $countStmt->bindValue($key, $value);
    }

    $countStmt->execute();
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalCount / $limit);

    // Ajout de l'ordre et de la pagination à la requête principale
    $allowedSortColumns = ['id', 'product_name', 'barcode', 'category', 'unit', 'quantity', 'quantity_reserved'];
    if (!in_array($sortBy, $allowedSortColumns)) {
        $sortBy = 'id';
    }

    $sql .= " ORDER BY p.$sortBy $sortOrder LIMIT :limit OFFSET :offset";

    // Préparation de la requête principale
    $stmt = $pdo->prepare($sql);

    // Liaison des paramètres pour la requête principale
    foreach ($searchParams as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    // Exécution de la requête
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ⭐ MISE À JOUR : Traitement des chemins d'images
    foreach ($products as &$product) {
        if (!empty($product['product_image'])) {
            // Vérifier si c'est un nouveau chemin (commence par /public/)
            if (strpos($product['product_image'], '/public/') === 0) {
                // Convertir le chemin absolu en chemin relatif depuis le dossier stock
                $product['product_image'] = '../../public/uploads/products/' . basename($product['product_image']);
            }
            // Si c'est un ancien chemin (uploads/products/), l'ajuster
            else if (strpos($product['product_image'], 'uploads/products/') === 0) {
                $product['product_image'] = '../../public/' . $product['product_image'];
            }
            // Vérifier si le fichier existe, sinon mettre à null
            if (!file_exists($product['product_image'])) {
                $product['product_image'] = null;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'products' => $products,
        'pagination' => [
            'totalItems' => $totalCount,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'itemsPerPage' => $limit
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => "Erreur de connexion à la base de données: " . $e->getMessage()
    ]);
}
?>