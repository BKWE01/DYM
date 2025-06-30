<?php
// Désactiver l'affichage des avertissements pour éviter les problèmes avec FPDF
error_reporting(E_ERROR | E_PARSE);

// Modifier le Content-Type pour que le navigateur reconnaisse le PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Expression_Besoin.pdf"');

require_once('fpdf/fpdf.php');

// Connexion à la base de données
include_once '../database/connection.php';

try {
    // Récupération de l'ID de l'expression depuis l'URL
    $id = isset($_GET['id']) ? trim($_GET['id']) : '';

    if (empty($id)) {
        throw new Exception('ID invalide.');
    }

    // Préparer et exécuter la requête pour obtenir les détails de l'expression
    $stmt = $pdo->prepare("
    SELECT id, idExpression, designation, unit, quantity, qt_stock, qt_acheter, prix_unitaire, montant, fournisseur, modePaiement, type, created_at, user_emet, user_stock, user_achat
    FROM expression_dym
    WHERE idExpression = :id
    ORDER BY type
    ");
    $stmt->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt->execute();

    $expressions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($expressions)) {
        throw new Exception('Aucune expression trouvée pour cet ID.');
    }

    // Calculer le montant total
    $totalGeneral = 0;
    foreach ($expressions as $expression) {
        if (isset($expression['montant']) && is_numeric($expression['montant'])) {
            $totalGeneral += floatval($expression['montant']);
        }
    }

    // La date `created_at` est récupérée depuis les expressions
    $createdAt = !empty($expressions) ? date('d/m/Y', strtotime($expressions[0]['created_at'])) : date('d/m/Y');

    // Préparer et exécuter la requête pour obtenir les détails du projet
    $stmt_projet = $pdo->prepare("
        SELECT code_projet, nom_client, description_projet, sitgeo, chefprojet
        FROM identification_projet
        WHERE idExpression = :id
    ");
    $stmt_projet->bindParam(':id', $id, PDO::PARAM_STR);
    $stmt_projet->execute();

    $projetDetails = $stmt_projet->fetch(PDO::FETCH_ASSOC);

    if (!$projetDetails) {
        throw new Exception('Détails du projet non trouvés pour cet ID.');
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
    $stmt_users->bindParam(':user_emet_id', $userEmetId, PDO::PARAM_INT);
    $stmt_users->bindParam(':user_stock_id', $userStockId, PDO::PARAM_INT);
    $stmt_users->bindParam(':user_achat_id', $userAchatId, PDO::PARAM_INT);
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

} catch (PDOException $e) {
    $error = $e->getMessage();
} catch (Exception $e) {
    $error = $e->getMessage();
}

if (!isset($error)) {
    class PDF extends FPDF
    {
        public $idExpression;
        public $createdAt;
        public $projetDetails;
        public $signatures;
        public $isLastPage = false; // Nouvelle variable pour savoir si c'est la dernière page
        public $isFirstPage = true; // Nouvelle propriété pour contrôler l'affichage de l'en-tête
        public $totalGeneral = 0; // Total général des expressions

        function __construct($idExpression, $createdAt, $projetDetails, $signatures, $totalGeneral)
        {
            parent::__construct();
            $this->idExpression = $idExpression;
            $this->createdAt = $createdAt;
            $this->projetDetails = $projetDetails;
            $this->signatures = $signatures;
            $this->totalGeneral = $totalGeneral;
        }

        // Fonction pour convertir les caractères spéciaux en ISO-8859-1 pour FPDF
        function strConvert($str)
        {
            if ($str === null)
                return '';
            return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $str);
        }

        function Header()
        {
            if ($this->isFirstPage) { // Afficher l'en-tête seulement sur la première page
                // Définir la hauteur de l'en-tête
                $headerHeight = 20;

                // Largeur totale de la page
                $pageWidth = $this->GetPageWidth() - 20; // Soustraction pour marges

                // Largeur des colonnes
                $colWidthLogo = $pageWidth * 0.2;    // 20% pour le logo
                $colWidthText = $pageWidth * 0.6;    // 60% pour le texte
                $colWidthDate = $pageWidth * 0.2;    // 20% pour la date

                // Bordure et police pour l'en-tête
                $this->SetFont('Arial', 'B', 10);
                $this->SetLineWidth(0.2);

                // Première colonne : logo
                $this->Cell($colWidthLogo, $headerHeight, '', 1, 0); // Bordure pour la cellule du logo
                $this->Image('logo.png', 15, 12, 30); // Ajustez les coordonnées et la taille du logo

                // Deuxième colonne : Texte centré
                $this->Cell($colWidthText, $headerHeight, $this->strConvert('FICHE D\'EXPRESSION DE BESOIN POUR LES PROJETS'), 1, 0, 'C');

                // Troisième colonne : Date created_at
                $this->Cell($colWidthDate, $headerHeight, $this->strConvert('Date: ' . $this->createdAt), 1, 1, 'C');

                // Espacement après l'en-tête
                $this->Ln(10);

                // Changer l'état de isFirstPage à false
                $this->isFirstPage = false;
            }
        }

        function ProjectTable()
        {
            // Largeur totale de la page
            $pageWidth = $this->GetPageWidth() - 20; // Soustraction pour marges

            // Largeur des colonnes
            $colWidth1 = $pageWidth * 0.3;    // 30% pour Détails
            $colWidth2 = $pageWidth * 0.7;    // 70% pour Informations

            // Bordure et police pour le tableau du projet
            $this->SetFont('Arial', 'B', 10);
            $this->SetLineWidth(0.2);

            // Titre du tableau
            $this->Cell($colWidth1, 10, $this->strConvert('I- IDENTIFICATION DU PROJET'), 0, 1, 'L');

            // Détails du projet avec textes fixes
            $this->SetFont('Arial', '', 10);
            $this->Cell($colWidth1, 10, $this->strConvert('Code du projet'), 1);
            $this->Cell($colWidth2, 10, $this->strConvert($this->projetDetails['code_projet']), 1, 1);

            $this->Cell($colWidth1, 10, $this->strConvert('Nom du client'), 1);
            $this->Cell($colWidth2, 10, $this->strConvert($this->projetDetails['nom_client']), 1, 1);

            $this->Cell($colWidth1, 10, $this->strConvert('Description du projet'), 1);
            $this->Cell($colWidth2, 10, $this->strConvert($this->projetDetails['description_projet']), 1, 1);

            $this->Cell($colWidth1, 10, $this->strConvert('Situation géographique du chantier'), 1);
            $this->Cell($colWidth2, 10, $this->strConvert($this->projetDetails['sitgeo']), 1, 1);

            $this->Cell($colWidth1, 10, $this->strConvert('Chef de Projet'), 1);
            $this->Cell($colWidth2, 10, $this->strConvert($this->projetDetails['chefprojet']), 1, 1);

            $this->Ln(10);
        }

        function ExpressionTable($expressions)
        {
            // Largeur totale de la page
            $pageWidth = $this->GetPageWidth() - 20; // Soustraction pour marges

            // Largeur des colonnes (en excluant la colonne Entité)
            $widths = [70, 25, 20, 20, 20, 20, 30, 35, 30]; // Ajustez ces valeurs pour mieux s'adapter à la page
            $totalWidth = array_sum($widths);

            // Ajustement de la largeur du tableau pour qu'il occupe toute la largeur de la page
            $scale = $pageWidth / $totalWidth;
            $scaledWidths = array_map(function ($width) use ($scale) {
                return $width * $scale;
            }, $widths);

            // Calculer la position X pour centrer le tableau
            $startX = ($pageWidth - array_sum($scaledWidths)) / 2;
            $startX += 10; // Pousse le tableau un peu à droite

            // Titre du tableau des expressions
            $this->SetFont('Arial', 'B', 10);
            $this->SetX($startX); // Alignement du début de la cellule
            $this->Cell(array_sum($scaledWidths), 10, $this->strConvert('II- DÉTAILS DES EXPRESSIONS'), 0, 1, 'L');

            // En-têtes du tableau des expressions
            $this->SetFont('Times', 'B', 5);
            $this->SetX($startX); // Alignement du début de la cellule
            $this->Cell($scaledWidths[0], 7, $this->strConvert('DESIGNATION'), 1);
            $this->Cell($scaledWidths[1], 7, $this->strConvert('UNITE'), 1);
            $this->Cell($scaledWidths[2], 7, $this->strConvert('Qt DEMANDE'), 1);
            $this->Cell($scaledWidths[3], 7, $this->strConvert('Qt STOCK'), 1);
            $this->Cell($scaledWidths[4], 7, $this->strConvert('Qt A CHER'), 1);
            $this->Cell($scaledWidths[5], 7, $this->strConvert('PU'), 1);
            $this->Cell($scaledWidths[6], 7, $this->strConvert('MONTANT'), 1);
            $this->Cell($scaledWidths[7], 7, $this->strConvert('FOURNISSEUR'), 1);
            $this->Cell($scaledWidths[8], 7, $this->strConvert('MODE PAIEMENT'), 1);
            $this->Ln();

            // Données des expressions
            $this->SetFont('Times', '', 6);

            $currentType = '';  // Variable pour stocker le type actuel
            $currentSubtotal = 0; // Sous-total pour le type actuel
            $currentTypeCount = 0; // Compteur de lignes pour le type actuel

            foreach ($expressions as $expression) {
                // Vérifier l'espace disponible avant d'ajouter un nouveau type
                if ($this->GetY() + 10 > $this->PageBreakTrigger - 40) { // 40 pour le footer
                    $this->AddPage();
                    $this->Footer(); // Appeler le footer sur la nouvelle page
                }

                // Si le type change, on affiche une ligne de type avec un arrière-plan coloré
                if ($expression['type'] !== $currentType) {
                    // Afficher le sous-total pour le type précédent
                    if ($currentTypeCount > 0) {
                        $this->SetFillColor(200, 220, 255); // Couleur d'arrière-plan pour le sous-total
                        $this->SetX($startX);
                        $this->Cell($scaledWidths[0], 6, $this->strConvert('Sous-total pour ' . $currentType), 1, 0, 'L', true);
                        for ($i = 1; $i <= 5; $i++) {
                            $this->Cell($scaledWidths[$i], 6, '', 1, 0, 'C', true);
                        }
                        $this->Cell($scaledWidths[6], 6, number_format(floatval($currentSubtotal), 0, ',', ' ') . ' FCFA', 1, 0, 'R', true);
                        for ($i = 7; $i <= 8; $i++) {
                            $this->Cell($scaledWidths[$i], 6, '', 1, 0, 'C', true);
                        }
                        $this->Ln();

                        // Réinitialiser le sous-total et le compteur de lignes
                        $currentSubtotal = 0;
                        $currentTypeCount = 0;
                    }

                    // Changer le type courant
                    $currentType = $expression['type'];
                    $this->SetFillColor(220, 220, 220); // Couleur d'arrière-plan pour la ligne du type
                    $this->SetX($startX);
                    $this->Cell(array_sum($scaledWidths), 6, $this->strConvert($currentType), 1, 1, 'L', true);
                }

                // Remplir les données de l'expression
                $this->SetX($startX);
                $this->Cell($scaledWidths[0], 6, $this->strConvert($expression['designation']), 1);
                $this->Cell($scaledWidths[1], 6, $this->strConvert($expression['unit']), 1);
                $this->Cell($scaledWidths[2], 6, $this->strConvert($expression['quantity']), 1);
                $this->Cell($scaledWidths[3], 6, $this->strConvert($expression['qt_stock']), 1);
                $this->Cell($scaledWidths[4], 6, $this->strConvert($expression['qt_acheter']), 1);
                $this->Cell($scaledWidths[5], 6, $this->strConvert($expression['prix_unitaire']), 1);
                $this->Cell($scaledWidths[6], 6, number_format(floatval($expression['montant']), 0, ',', ' '), 1, 0, 'R'); // Aligné à droite
                $this->Cell($scaledWidths[7], 6, $this->strConvert($expression['fournisseur']), 1);
                $this->Cell($scaledWidths[8], 6, $this->strConvert($expression['modePaiement']), 1);
                $this->Ln();

                // Accumuler le sous-total
                $currentSubtotal += floatval($expression['montant']); // Convertir montant
                $currentTypeCount++;
            }

            // Afficher le sous-total pour le dernier type
            if ($currentTypeCount > 0) {
                $this->SetFillColor(200, 220, 255); // Couleur d'arrière-plan pour le sous-total
                $this->SetX($startX);
                $this->Cell($scaledWidths[0], 6, $this->strConvert('Sous-total pour ' . $currentType), 1, 0, 'L', true);
                for ($i = 1; $i <= 5; $i++) {
                    $this->Cell($scaledWidths[$i], 6, '', 1, 0, 'C', true);
                }
                $this->Cell($scaledWidths[6], 6, number_format(floatval($currentSubtotal), 0, ',', ' ') . ' FCFA', 1, 0, 'R', true);
                for ($i = 7; $i <= 8; $i++) {
                    $this->Cell($scaledWidths[$i], 6, '', 1, 0, 'C', true);
                }
                $this->Ln();
            }

            // Ajouter le TOTAL GÉNÉRAL
            // $this->Ln(5); // Espace avant le total général

            // Créer une nouvelle ligne pour le total général avec sa propre mise en page
            $this->SetFillColor(169, 169, 169); // Gris pour le fond
            $this->SetFont('Arial', 'B', 8);

            // Calculer la largeur disponible - utiliser seulement la partie du tableau nécessaire
            // Exclure les colonnes de fournisseur et mode de paiement
            $widthBeforeMontant = array_sum(array_slice($scaledWidths, 0, 6));

            // Positionner à l'endroit où démarre le tableau
            $this->SetX($startX);

            // Cellule pour le label du total (occupe tout l'espace avant la colonne montant)
            $this->Cell($widthBeforeMontant, 8, $this->strConvert('TOTAL GÉNÉRAL'), 1, 0, 'R', true);

            // Cellule pour le montant (même largeur que la colonne montant du tableau)
            $this->Cell($scaledWidths[9], 8, number_format($this->totalGeneral, 0, ',', ' ') . ' FCFA', 1, 1, 'R', true);

            // Définir que c'est la dernière page après avoir ajouté toutes les expressions
            $this->isLastPage = true;
        }

        function Footer()
        {
            if ($this->isLastPage) {
                // Définir la position du footer
                $footerHeight = 40;
                $this->SetY(-($footerHeight + 10)); // Positionne le footer avec 10mm de marge en bas

                // Largeur totale de la page
                $pageWidth = $this->GetPageWidth() - 20; // Soustraction pour les marges

                // Largeur des colonnes
                $colWidth = $pageWidth / 3; // Diviser la largeur totale en 3 colonnes égales

                // Bordure et police pour le footer
                $this->SetFont('Arial', '', 8);
                $this->SetLineWidth(0.2);

                // Première ligne du footer (Titres)
                $this->Cell($colWidth, 10, $this->strConvert('VISA Resp. BE'), 1, 0, 'C');
                $this->Cell($colWidth, 10, $this->strConvert('VISA Resp. Stock'), 1, 0, 'C');
                $this->Cell($colWidth, 10, $this->strConvert('VISA Resp. Achat'), 1, 1, 'C');

                // Deuxième ligne du footer (Signatures)
                // Remplissage des colonnes avec les images des signatures ou une cellule vide si pas de signature
                $this->Cell($colWidth, 30, !empty($this->signatures['user_emet']) ? $this->Image('../uploads/' . $this->signatures['user_emet'], $this->GetX(), $this->GetY(), $colWidth, 30) : '', 1, 0, 'C');
                $this->Cell($colWidth, 30, !empty($this->signatures['user_stock']) ? $this->Image('../uploads/' . $this->signatures['user_stock'], $this->GetX(), $this->GetY(), $colWidth, 30) : '', 1, 0, 'C');
                $this->Cell($colWidth, 30, !empty($this->signatures['user_achat']) ? $this->Image('../uploads/' . $this->signatures['user_achat'], $this->GetX(), $this->GetY(), $colWidth, 30) : '', 1, 1, 'C');
            }
        }
    }

    // Création du PDF avec la date `created_at` de `expression_dym`
    $pdf = new PDF($id, $createdAt, $projetDetails, $signatures, $totalGeneral);
    $pdf->AddPage();

    // Ajout des données
    $pdf->ProjectTable();
    $pdf->ExpressionTable($expressions);

    // Envoyer le PDF au navigateur pour affichage au lieu de téléchargement
    $pdf->Output();

} else {
    // En cas d'erreur, renvoyer une réponse JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => $error]);
}
?>