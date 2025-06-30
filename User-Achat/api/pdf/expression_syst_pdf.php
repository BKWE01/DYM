<?php
/**
 * Générateur PDF corrigé pour les expressions de besoins système
 * 
 * Version fonctionnelle basée sur l'ancien code qui marchait
 * avec corrections UNIQUEMENT pour la responsivité du texte
 * 
 * @package DYM_MANUFACTURE
 * @subpackage PDF_Generation
 * @version 2.2 - Version Fonctionnelle
 * @author DYM Development Team
 */

require_once('../../../fpdf/fpdf.php');

// Configuration des en-têtes
header('Content-Type: text/html; charset=utf-8');

// Connexion à la base de données
include_once '../../../database/connection.php';

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
        SELECT b.id, b.idBesoin, b.designation_article, b.qt_demande, b.qt_stock, b.qt_acheter, 
               b.user_emet, b.user_stock, b.user_achat, p.unit
        FROM besoins b
        LEFT JOIN products p ON b.product_id = p.id
        WHERE b.idBesoin = :id
        ORDER BY b.designation_article
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

    $userIds = array_filter([$userEmetId, $userStockId, $userAchatId]);

    if (!empty($userIds)) {
        $placeholders = rtrim(str_repeat('?,', count($userIds)), ',');
        $stmt_users = $pdo->prepare("
            SELECT id, signature
            FROM users_exp
            WHERE id IN ($placeholders)
        ");
        
        $i = 1;
        foreach ($userIds as $userId) {
            $stmt_users->bindValue($i++, $userId, PDO::PARAM_INT);
        }
        
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
            } elseif ($user['id'] == $userStockId) {
                $signatures['user_stock'] = $user['signature'];
            }
        }
    } else {
        $signatures = [
            'user_emet' => '',
            'user_stock' => '',
            'user_achat' => ''
        ];
    }

} catch (PDOException $e) {
    die('Erreur de connexion : ' . $e->getMessage());
} catch (Exception $e) {
    die('Erreur : ' . $e->getMessage());
}

/**
 * Classe PDF - RETOUR À LA VERSION QUI FONCTIONNAIT
 * avec seulement des améliorations de responsivité du texte
 */
class PDF extends FPDF {
    public $signatures;

    /**
     * En-tête - EXACTEMENT comme l'ancien code
     */
    function Header() {
        // Logo
        $this->Image('../../../public/logo.png', 25, 12, 30);
        
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

    /**
     * Fonction pour créer le tableau des demandes
     * AMÉLIORÉE pour la gestion du texte long
     */
    function demandeTable($service_demandeur, $nom_prenoms, $date_demande, $motif_demande) {
        // Définir la police pour le tableau
        $this->SetFont('Times', 'B', 12);

        // En-tête du tableau
        $this->Cell(70, 10, utf8_decode('SERVICE DEMANDEUR'), 1, 0, 'C');
        $this->Cell(70, 10, utf8_decode('NOM & PRÉNOMS DEMANDEUR'), 1, 0, 'C');
        $this->Cell(50, 10, utf8_decode('DATE DEMANDE'), 1, 1, 'C');

        // Contenu du tableau avec ajustement du texte
        $this->SetFont('Times', '', 12);
        
        // Ajuster le texte si trop long (NOUVELLE FONCTIONNALITÉ)
        $service_text = $this->fitTextToCell($service_demandeur, 66);
        $nom_text = $this->fitTextToCell($nom_prenoms, 66);
        
        $this->Cell(70, 10, utf8_decode($service_text), 1, 0, 'C');
        $this->Cell(70, 10, utf8_decode($nom_text), 1, 0, 'C');
        $this->Cell(50, 10, utf8_decode(date('d/m/Y', strtotime($date_demande))), 1, 1, 'C');

        // Créer une cellule avec bordure pour "Motif de la demande" et son contenu
        $this->Cell(190, 10, '', 1, 0); // Cellule pour bordure complète

        // Positionner le curseur
        $this->SetX($this->GetX() - 190); // Revenir en arrière dans la cellule

        // Texte "Motif de la demande :" souligné
        $this->SetFont('Times', 'U', 12); // Police soulignée
        $this->Cell(50, 10, utf8_decode('Motif de la demande :'), 0, 0, 'L');

        // Texte du motif avec ajustement (NOUVELLE FONCTIONNALITÉ)
        $this->SetFont('Times', '', 12); // Police normale
        $motif_text = $this->fitTextToCell($motif_demande, 136);
        $this->Cell(140, 10, utf8_decode($motif_text), 0, 1, 'L');
    }

    /**
     * Fonction pour créer le tableau des besoins
     * AMÉLIORÉE avec gestion de l'espace et évitement de conflit avec signatures
     */
    function besoinsTable($expressions) {
        // Calcul de la largeur optimale pour la désignation
        $maxLength = 0;
        foreach ($expressions as $expr) {
            $length = strlen($expr['designation_article']);
            if ($length > $maxLength) {
                $maxLength = $length;
            }
        }
        
        // Ajustement des largeurs selon le contenu
        if ($maxLength > 50) {
            $designationWidth = 140; // Plus large pour texte long
            $qtyWidth = 16.67;        // Plus étroit pour quantités
        } else {
            $designationWidth = 130;  // Largeur standard
            $qtyWidth = 20;           // Largeur standard
        }

        // En-tête du tableau
        $this->SetFont('Times', 'B', 10); // Police légèrement plus petite
        $this->Cell($designationWidth, 8, utf8_decode('Désignation'), 1, 0, 'C');
        $this->Cell($qtyWidth, 8, utf8_decode('Qté Dem.'), 1, 0, 'C');
        $this->Cell($qtyWidth, 8, utf8_decode('Qté Stock'), 1, 0, 'C');
        $this->Cell($qtyWidth, 8, utf8_decode('Qté Achat'), 1, 1, 'C');

        // Contenu du tableau avec hauteur réduite
        $this->SetFont('Times', '', 9); // Police plus petite pour économiser l'espace
        $rowHeight = 7; // Hauteur réduite des lignes
        
        foreach ($expressions as $row) {
            // Vérifier l'espace disponible avant chaque ligne
            $spaceNeeded = $rowHeight + 55; // Espace pour la ligne + signatures
            if ($this->GetY() + $spaceNeeded > $this->GetPageHeight() - 20) {
                // Si pas assez d'espace, réduire encore la hauteur
                $rowHeight = 6;
            }
            
            // Définir l'unité
            $unit = isset($row['unit']) && !empty($row['unit']) ? $row['unit'] : '';
            
            // Ajouter l'unité aux quantités si disponible
            $qt_demande = $row['qt_demande'] . ($unit ? ' ' . $unit : '');
            $qt_stock = $row['qt_stock'] . ($unit ? ' ' . $unit : '');
            $qt_acheter = $row['qt_acheter'] . ($unit ? ' ' . $unit : '');
            
            // AMÉLIORATION : Ajuster la désignation à la largeur
            $designation = $this->fitTextToCell($row['designation_article'], $designationWidth - 4);
            
            $this->Cell($designationWidth, $rowHeight, utf8_decode($designation), 1, 0, 'L');
            $this->Cell($qtyWidth, $rowHeight, utf8_decode($qt_demande), 1, 0, 'C');
            $this->Cell($qtyWidth, $rowHeight, utf8_decode($qt_stock), 1, 0, 'C');
            $this->Cell($qtyWidth, $rowHeight, utf8_decode($qt_acheter), 1, 1, 'C');
        }
        
        // Ajouter un espacement avant les signatures pour éviter le chevauchement
        $this->Ln(5);
    }

    /**
     * Pied de page - OPTIMISÉ pour éviter les conflits
     */
    function Footer() {
        // S'assurer qu'on a assez d'espace pour les signatures
        $footerHeight = 45;
        $currentY = $this->GetY();
        
        // Si on est trop bas, ajuster la position
        if ($currentY > $this->GetPageHeight() - $footerHeight - 15) {
            $this->SetY($this->GetPageHeight() - $footerHeight - 10);
        } else {
            // Sinon, positionner normalement
            $this->SetY(-($footerHeight + 5));
        }

        // Largeur totale de la page
        $pageWidth = $this->GetPageWidth() - 20;
        $colWidth = $pageWidth / 3;

        // Bordure et police pour le footer
        $this->SetFont('Arial', 'B', 9);
        $this->SetLineWidth(0.2);

        // Première ligne du footer (Titres)
        $this->Cell($colWidth, 8, utf8_decode('VISA Demandeur'), 1, 0, 'C');
        $this->Cell($colWidth, 8, utf8_decode('VISA Resp. Stock'), 1, 0, 'C');
        $this->Cell($colWidth, 8, utf8_decode('VISA Resp. Achat'), 1, 1, 'C');

        // Deuxième ligne du footer (Signatures) - Plus compacte
        $startX = $this->GetX();
        $startY = $this->GetY();
        $signatureHeight = 25; // Hauteur réduite
        
        // Dessiner les cellules vides d'abord
        $this->Cell($colWidth, $signatureHeight, '', 1, 0, 'C');
        $this->Cell($colWidth, $signatureHeight, '', 1, 0, 'C');
        $this->Cell($colWidth, $signatureHeight, '', 1, 1, 'C');
        
        // Ajouter les images de signature avec taille optimisée
        if (!empty($this->signatures['user_emet']) && file_exists('../../../uploads/' . $this->signatures['user_emet'])) {
            try {
                $this->Image('../../../uploads/' . $this->signatures['user_emet'], 
                           $startX + 3, $startY + 2, $colWidth - 6, $signatureHeight - 4);
            } catch (Exception $e) {
                // Signature non lisible, continuer
            }
        }
        
        if (!empty($this->signatures['user_stock']) && file_exists('../../../uploads/' . $this->signatures['user_stock'])) {
            try {
                $this->Image('../../../uploads/' . $this->signatures['user_stock'], 
                           $startX + $colWidth + 3, $startY + 2, $colWidth - 6, $signatureHeight - 4);
            } catch (Exception $e) {
                // Signature non lisible, continuer
            }
        }
        
        if (!empty($this->signatures['user_achat']) && file_exists('../../../uploads/' . $this->signatures['user_achat'])) {
            try {
                $this->Image('../../../uploads/' . $this->signatures['user_achat'], 
                           $startX + (2 * $colWidth) + 3, $startY + 2, $colWidth - 6, $signatureHeight - 4);
            } catch (Exception $e) {
                // Signature non lisible, continuer
            }
        }
    }

    /**
     * NOUVELLE MÉTHODE : Ajuster le texte à la largeur de cellule
     */
    private function fitTextToCell($text, $maxWidth) {
        $this->SetFont('Times', '', 12);
        
        // Si le texte rentre, le retourner tel quel
        if ($this->GetStringWidth(utf8_decode($text)) <= $maxWidth) {
            return $text;
        }
        
        // Sinon, le raccourcir intelligemment
        $words = explode(' ', $text);
        $result = '';
        
        foreach ($words as $word) {
            $test = $result . ($result ? ' ' : '') . $word;
            if ($this->GetStringWidth(utf8_decode($test)) > $maxWidth - 4) {
                break;
            }
            $result = $test;
        }
        
        // Si aucun mot ne rentre, tronquer le premier
        if (empty($result) && !empty($words[0])) {
            while ($this->GetStringWidth(utf8_decode($words[0])) > $maxWidth - 4) {
                $words[0] = substr($words[0], 0, -1);
            }
            $result = $words[0];
        }
        
        return $result . (strlen($result) < strlen($text) ? '...' : '');
    }
}

// Créer une instance de PDF et ajouter une page
$pdf = new PDF();
$pdf->signatures = $signatures;
$pdf->AddPage();

// Utilisation des données récupérées de la base de données
$pdf->demandeTable($service_demandeur, $nom_prenoms, $date_demande, $motif_demande);

// Ajout du tableau des besoins avec espacement optimisé
$pdf->Ln(8); // Espacement réduit
$pdf->besoinsTable($expressions);

// Génération du nom de fichier personnalisé et descriptif
$fileName = 'Expression_Besoins_Systeme_' . $id . '_' . date('Y-m-d') . '.pdf';

// Sortie du fichier PDF avec nom personnalisé
$pdf->Output($fileName, 'I'); // 'I' = affichage dans le navigateur avec nom spécifique
// Alternative pour forcer le téléchargement : $pdf->Output($fileName, 'D');
?>