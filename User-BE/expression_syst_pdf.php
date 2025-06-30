<?php
require_once('fpdf/fpdf.php');

// Ajout de l'en-tête UTF-8
header('Content-Type: text/html; charset=utf-8');

// Connexion à la base de données
include_once '../database/connection.php';

try {

    // Récupération de l'ID de l'expression depuis l'URL
    $id = isset($_GET['id']) ? trim($_GET['id']) : '';

    if (empty($id)) {
        throw new Exception('ID invalide.');
    }

    // Préparer et exécuter la requête pour obtenir les détails de la demande
    $stmt_projet = $pdo->prepare("
        SELECT idBesoin, service_demandeur, nom_prenoms, date_demande, motif_demande
        FROM demandeur
        WHERE idBesoin = :id
    ");
    $stmt_projet->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt_projet->execute();

    // Récupérer les détails de la demande
    $projetDetails = $stmt_projet->fetch(PDO::FETCH_ASSOC);

    if (!$projetDetails) {
        throw new Exception('Détails du projet non trouvés pour cet ID.');
    }

    // Extraction des données
    $service_demandeur = $projetDetails['service_demandeur'];
    $nom_prenoms = $projetDetails['nom_prenoms'];
    $date_demande = $projetDetails['date_demande'];
    $motif_demande = $projetDetails['motif_demande'];

    // Préparer et exécuter la requête pour obtenir les détails de l'expression
    $stmt = $pdo->prepare("
    SELECT id, idBesoin, designation_article, caracteristique, qt_demande, qt_stock, qt_acheter, user_emet, user_stock, user_achat
    FROM besoins
    WHERE idBesoin = :id
    ");
    $stmt->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt->execute();

    $expressions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($expressions)) {
        throw new Exception('Aucune expression trouvée pour cet ID.');
    }

    // Récupérer les signatures des utilisateurs
    $userEmetId = $expressions[0]['user_emet'];
    $userStockId = $expressions[0]['user_stock'];
    $userAchatId = $expressions[0]['user_achat'];

    $stmt_users = $pdo->prepare("
        SELECT id, signature
        FROM users_exp
        WHERE id IN (:user_emet_id, :user_stock_id, :user_achat_id)
    ");
    
    // Utilisation de bindValue pour le tableau de valeurs
    $stmt_users->bindValue(':user_emet_id', $userEmetId, PDO::PARAM_INT);
    $stmt_users->bindValue(':user_stock_id', $userStockId, PDO::PARAM_INT);
    $stmt_users->bindValue(':user_achat_id', $userAchatId, PDO::PARAM_INT);
    $stmt_users->execute();

    $usersSignatures = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    $signatures = [
        'user_emet' => '',
        'user_stock' => '',
        'user_achat' => ''
    ];

    foreach ($usersSignatures as $user) {
        if ($user['id'] == $userEmetId) {
            $signatures['user_emet'] = $user['signature'];
        } elseif ($user['id'] == $userAchatId) {
            $signatures['user_achat'] = $user['signature'];
        }
        elseif ($user['id'] == $userStockId) {
            $signatures['user_stock'] = $user['signature'];
        }
    }

} catch (PDOException $e) {
    die('Erreur de connexion : ' . $e->getMessage());
} catch (Exception $e) {
    die('Erreur : ' . $e->getMessage());
}

// Définition de la classe PDF
class PDF extends FPDF {
    public $signatures; // Déclarer la variable pour les signatures

    // En-tête
    function Header() {
        // Logo
        $this->Image('logo.png', 25, 12, 30); // Ajustez la position et la taille du logo
        
        // Créer le tableau d'en-tête
        $this->SetFont('Times', 'B', 12);
        $this->Cell(50, 20, '', 1, 0, 'C'); // Colonne Logo
        $this->Cell(140, 20, utf8_decode('FICHE D\'EXPRESSION DE BESOINS'), 1, 1, 'C');

        // Police normale
        $this->SetFont('Times', '', 12);

        // Deuxième ligne du tableau d'en-tête
        $this->Cell(50, 20, utf8_decode('DYM MANUFACTURE'), 1, 0, 'C');
        $this->Cell(140, 20, '', 1, 1, 'C'); // Cellule vide pour la mise en forme

        // Texte en plusieurs lignes
        $this->SetXY($this->GetX() - 160, $this->GetY() - 20); // Remonter à la cellule vide
        $this->MultiCell(140, 10, utf8_decode('SYSTEME DE MANAGEMENT INTEGRE' . "\n" . 'QUALITÉ - SÉCURITÉ - ENVIRONNEMENT'), 1, 'C');

        // Espace après l'en-tête
        $this->Ln(10);
    }

    // Fonction pour créer le tableau des demandes
    function demandeTable($service_demandeur, $nom_prenoms, $date_demande, $motif_demande) {
        // Définir la police pour le tableau
        $this->SetFont('Times', 'B', 12);

        // En-tête du tableau
        $this->Cell(70, 10, utf8_decode('SERVICE DEMANDEUR'), 1, 0, 'C');
        $this->Cell(70, 10, utf8_decode('NOM & PRÉNOMS DEMANDEUR'), 1, 0, 'C');
        $this->Cell(50, 10, utf8_decode('DATE DEMANDE'), 1, 1, 'C');

        // Contenu du tableau
        $this->SetFont('Times', '', 12);
        $this->Cell(70, 10, utf8_decode($service_demandeur), 1, 0, 'C');
        $this->Cell(70, 10, utf8_decode($nom_prenoms), 1, 0, 'C');
        $this->Cell(50, 10, utf8_decode($date_demande), 1, 1, 'C');

        // Créer une cellule avec bordure pour "Motif de la demande" et son contenu
        $this->Cell(190, 10, '', 1, 0); // Cellule pour bordure complète

        // Positionner le curseur
        $this->SetX($this->GetX() - 190); // Revenir en arrière dans la cellule

        // Texte "Motif de la demande :" souligné
        $this->SetFont('Times', 'U', 12); // Police soulignée
        $this->Cell(50, 10, utf8_decode('Motif de la demande :'), 0, 0, 'L');

        // Texte du motif
        $this->SetFont('Times', '', 12); // Police normale
        $this->Cell(140, 10, utf8_decode($motif_demande), 0, 1, 'L');
    }

    // Fonction pour créer le tableau des besoins
    function besoinsTable($expressions) {
        // En-tête du tableau
        $this->SetFont('Times', 'B', 12);
        $this->Cell(100, 10, utf8_decode('Désignation'), 1, 0, 'C');
        $this->Cell(30, 10, utf8_decode('Qté Dem.'), 1, 0, 'C');
        $this->Cell(30, 10, utf8_decode('Qté Stock'), 1, 0, 'C');
        $this->Cell(30, 10, utf8_decode('Qté Acheter'), 1, 1, 'C');

        // Contenu du tableau
        $this->SetFont('Times', '', 12);
        foreach ($expressions as $row) {
            $this->Cell(100, 10, utf8_decode($row['designation_article']), 1, 0, 'C');
            $this->Cell(30, 10, utf8_decode($row['qt_demande']), 1, 0, 'C');
            $this->Cell(30, 10, utf8_decode($row['qt_stock']), 1, 0, 'C');
            $this->Cell(30, 10, utf8_decode($row['qt_acheter']), 1, 1, 'C');
        }
    }

    // Pied de page
    function Footer() {
        // Positionner le footer
        $footerHeight = 40;
        $this->SetY(-($footerHeight + 10)); // Positionner avec 10mm de marge en bas

        // Largeur totale de la page
        $pageWidth = $this->GetPageWidth() - 20; // Soustraction pour les marges

        // Largeur des colonnes
        $colWidth = $pageWidth / 3; // Diviser la largeur totale en 3 colonnes égales

        // Bordure et police pour le footer
        $this->SetFont('Arial', '', 8);
        $this->SetLineWidth(0.2);

        // Première ligne du footer (Titres)
        $this->Cell($colWidth, 10, utf8_decode('VISA Resp. BE'), 1, 0, 'C');
        $this->Cell($colWidth, 10, utf8_decode('VISA Resp. Stock'), 1, 0, 'C');
        $this->Cell($colWidth, 10, utf8_decode('VISA Resp. Achat'), 1, 1, 'C');

        // Deuxième ligne du footer (Signatures)
        $this->Cell($colWidth, 30, !empty($this->signatures['user_emet']) ? $this->Image('../uploads/' . $this->signatures['user_emet'], $this->GetX(), $this->GetY(), $colWidth, 30) : '', 1, 0, 'C');
        $this->Cell($colWidth, 30, !empty($this->signatures['user_stock']) ? $this->Image('../uploads/' . $this->signatures['user_stock'], $this->GetX(), $this->GetY(), $colWidth, 30) : '', 1, 0, 'C');
        $this->Cell($colWidth, 30, !empty($this->signatures['user_achat']) ? $this->Image('../uploads/' . $this->signatures['user_achat'], $this->GetX(), $this->GetY(), $colWidth, 30) : '', 1, 1, 'C');
    }
}

// Créer une instance de PDF et ajouter une page
$pdf = new PDF();
$pdf->signatures = $signatures; // Passer les signatures à l'objet PDF
$pdf->AddPage();

// Utilisation des données récupérées de la base de données
$pdf->demandeTable($service_demandeur, $nom_prenoms, $date_demande, $motif_demande);

// Ajout du tableau des besoins
$pdf->Ln(10); // Saut de ligne avant d'ajouter le tableau des besoins
$pdf->besoinsTable($expressions);

// Sortie du fichier PDF
$pdf->Output();
?>
