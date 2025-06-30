<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: ./../../../index.php");
    exit();
}

// Inclure TCPDF
require_once '../../../vendor/tcpdf/tcpdf.php';
// Connexion à la base de données
include_once '../../../database/connection.php';

// Filtres
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, low, out
$category = isset($_GET['category']) ? $_GET['category'] : '';
$productName = isset($_GET['product_name']) ? $_GET['product_name'] : '';

try {
    // Construire la requête SQL avec filtres
    $sql = "SELECT 
                p.barcode, 
                p.product_name, 
                c.libelle as category_name, 
                p.quantity, 
                COALESCE(p.quantity_reserved, 0) as quantity_reserved, 
                COALESCE(p.unit, 'unité') as unit
            FROM products p
            LEFT JOIN categories c ON p.category = c.id
            WHERE 1=1";

    $params = [];

    // Appliquer les filtres
    if ($filter === 'low') {
        $sql .= " AND p.quantity > 0 AND p.quantity <= 10";
    } elseif ($filter === 'out') {
        $sql .= " AND p.quantity = 0";
    }

    if (!empty($category)) {
        $sql .= " AND p.category = :category";
        $params[':category'] = $category;
    }

    if (!empty($productName)) {
        $sql .= " AND p.product_name LIKE :productName";
        $params[':productName'] = "%$productName%";
    }

    $sql .= " ORDER BY p.product_name";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Créer un PDF
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8');

    // Métadonnées
    $pdf->SetCreator('DYM Manufacture');
    $pdf->SetAuthor('Bureau d\'Études');
    $pdf->SetTitle('Inventaire des Produits');
    $pdf->SetSubject('État du stock au ' . date('d/m/Y'));

    // En-tête et pied de page
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->setFooterData(array(0, 0, 0), array(0, 0, 0));
    $pdf->setFooterFont(array('helvetica', '', 8));
    $pdf->setFooterMargin(10);

    // Ajouter une page
    $pdf->AddPage();

    // Titre
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Inventaire des Produits - ' . date('d/m/Y'), 0, 1, 'C');

    // Filtres appliqués
    $pdf->SetFont('helvetica', 'I', 10);
    $filterText = 'Filtre: ';

    if ($filter === 'low') {
        $filterText .= 'Stock Faible';
    } elseif ($filter === 'out') {
        $filterText .= 'Rupture de Stock';
    } else {
        $filterText .= 'Tous les produits';
    }

    if (!empty($category)) {
        $catStmt = $pdo->prepare("SELECT libelle FROM categories WHERE id = :id");
        $catStmt->bindParam(':id', $category, PDO::PARAM_INT);
        $catStmt->execute();
        $catName = $catStmt->fetchColumn();
        $filterText .= ', Catégorie: ' . $catName;
    }

    if (!empty($productName)) {
        $filterText .= ', Nom: ' . $productName;
    }

    $pdf->Cell(0, 6, $filterText, 0, 1, 'C');
    $pdf->Ln(5);

    // En-têtes de colonnes
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(40, 7, 'Barcode', 1, 0, 'C', 1);
    $pdf->Cell(80, 7, 'Nom du Produit', 1, 0, 'C', 1);
    $pdf->Cell(50, 7, 'Catégorie', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Quantité', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Réservée', 1, 0, 'C', 1);
    $pdf->Cell(20, 7, 'Unité', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'État du Stock', 1, 1, 'C', 1);

    // Données
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(255, 255, 255);

    foreach ($products as $product) {
        // Déterminer l'état du stock
        $quantity = $product['quantity'];
        $stockStatus = '';
        $fillColor = array(255, 255, 255);

        if ($quantity == 0) {
            $stockStatus = 'Rupture';
            $fillColor = array(255, 200, 200);
        } elseif ($quantity < 3) {
            $stockStatus = 'Faible';
            $fillColor = array(255, 235, 156);
        } elseif ($quantity <= 10) {
            $stockStatus = 'Moyen';
            $fillColor = array(200, 220, 255);
        } else {
            $stockStatus = 'Élevé';
            $fillColor = array(200, 255, 200);
        }

        $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);

        $pdf->Cell(40, 6, $product['barcode'], 1, 0, 'L', 1);
        $pdf->Cell(80, 6, $product['product_name'], 1, 0, 'L', 1);
        $pdf->Cell(50, 6, $product['category_name'], 1, 0, 'L', 1);
        $pdf->Cell(25, 6, $product['quantity'], 1, 0, 'C', 1);
        $pdf->Cell(25, 6, $product['quantity_reserved'], 1, 0, 'C', 1);
        $pdf->Cell(20, 6, $product['unit'], 1, 0, 'C', 1);
        $pdf->Cell(30, 6, $stockStatus, 1, 1, 'C', 1);
    }

    // Sortie du PDF
    $pdfName = 'Inventaire_Produits_' . date('Y-m-d') . '.pdf';
    $pdf->Output($pdfName, 'D');

} catch (PDOException $e) {
    // Gérer l'erreur
    echo "Erreur: " . $e->getMessage();
}