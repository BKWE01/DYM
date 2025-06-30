<?php

/**
 * Configuration centralisée des couleurs pour les catégories de fournisseurs
 * DYM MANUFACTURE - Système de gestion intégré
 */

// Configuration des couleurs disponibles pour les badges
class ColorManager
{

    // Couleurs disponibles avec leurs noms et classes CSS
    private static $colors = [
        'badge-blue' => [
            'name' => 'Bleu',
            'class' => 'badge-blue',
            'hex' => '#3b82f6',
            'text_color' => '#ffffff'
        ],
        'badge-green' => [
            'name' => 'Vert',
            'class' => 'badge-green',
            'hex' => '#10b981',
            'text_color' => '#ffffff'
        ],
        'badge-purple' => [
            'name' => 'Violet',
            'class' => 'badge-purple',
            'hex' => '#8b5cf6',
            'text_color' => '#ffffff'
        ],
        'badge-orange' => [
            'name' => 'Orange',
            'class' => 'badge-orange',
            'hex' => '#f59e0b',
            'text_color' => '#ffffff'
        ],
        'badge-red' => [
            'name' => 'Rouge',
            'class' => 'badge-red',
            'hex' => '#ef4444',
            'text_color' => '#ffffff'
        ],
        'badge-gray' => [
            'name' => 'Gris',
            'class' => 'badge-gray',
            'hex' => '#6b7280',
            'text_color' => '#ffffff'
        ],
        'badge-pink' => [
            'name' => 'Rose',
            'class' => 'badge-pink',
            'hex' => '#ec4899',
            'text_color' => '#ffffff'
        ],
        'badge-indigo' => [
            'name' => 'Indigo',
            'class' => 'badge-indigo',
            'hex' => '#6366f1',
            'text_color' => '#ffffff'
        ],
        'badge-yellow' => [
            'name' => 'Jaune',
            'class' => 'badge-yellow',
            'hex' => '#facc15',
            'text_color' => '#1f2937'
        ],
        'badge-lime' => [
            'name' => 'Lime',
            'class' => 'badge-lime',
            'hex' => '#84cc16',
            'text_color' => '#ffffff'
        ],
        'badge-teal' => [
            'name' => 'Teal',
            'class' => 'badge-teal',
            'hex' => '#14b8a6',
            'text_color' => '#ffffff'
        ],
        'badge-cyan' => [
            'name' => 'Cyan',
            'class' => 'badge-cyan',
            'hex' => '#06b6d4',
            'text_color' => '#ffffff'
        ],
        'badge-brown' => [
            'name' => 'Marron',
            'class' => 'badge-brown',
            'hex' => '#a47148',
            'text_color' => '#ffffff'
        ],
        'badge-slate' => [
            'name' => 'Ardoise',
            'class' => 'badge-slate',
            'hex' => '#475569',
            'text_color' => '#ffffff'
        ],
        'badge-emerald' => [
            'name' => 'Émeraude',
            'class' => 'badge-emerald',
            'hex' => '#059669',
            'text_color' => '#ffffff'
        ]
    ];

    /**
     * Récupérer toutes les couleurs disponibles
     */
    public static function getAllColors()
    {
        return self::$colors;
    }

    /**
     * Récupérer les informations d'une couleur spécifique
     */
    public static function getColor($colorClass)
    {
        return isset(self::$colors[$colorClass]) ? self::$colors[$colorClass] : self::$colors['badge-blue'];
    }

    /**
     * Récupérer la classe CSS d'une couleur
     */
    public static function getColorClass($colorClass)
    {
        $color = self::getColor($colorClass);
        return $color['class'];
    }

    /**
     * Récupérer le nom d'une couleur
     */
    public static function getColorName($colorClass)
    {
        $color = self::getColor($colorClass);
        return $color['name'];
    }

    /**
     * Récupérer la couleur hexadécimale
     */
    public static function getColorHex($colorClass)
    {
        $color = self::getColor($colorClass);
        return $color['hex'];
    }

    /**
     * Vérifier si une couleur existe
     */
    public static function colorExists($colorClass)
    {
        return isset(self::$colors[$colorClass]);
    }

    /**
     * Récupérer une couleur aléatoire
     */
    public static function getRandomColor()
    {
        $colorKeys = array_keys(self::$colors);
        $randomKey = $colorKeys[array_rand($colorKeys)];
        return self::$colors[$randomKey];
    }

    /**
     * Obtenir les couleurs sous forme de tableau pour les select HTML
     */
    public static function getColorsForSelect()
    {
        $options = [];
        foreach (self::$colors as $class => $info) {
            $options[$class] = $info['name'];
        }
        return $options;
    }

    /**
     * Récupérer la couleur d'une catégorie depuis la base de données
     * avec fallback intelligent
     */
    public static function getCategoryColor($categoryName, $pdo = null)
    {
        if ($pdo) {
            try {
                $query = "SELECT couleur FROM categories_fournisseurs WHERE nom = :nom AND active = 1";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':nom', $categoryName);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result && self::colorExists($result['couleur'])) {
                    return $result['couleur'];
                }
            } catch (PDOException $e) {
                // Erreur silencieuse, utiliser le fallback
            }
        }

        // Fallback intelligent basé sur le nom de la catégorie
        return self::getSmartFallbackColor($categoryName);
    }

    /**
     * Fallback intelligent pour les couleurs basé sur le nom de la catégorie
     */
    private static function getSmartFallbackColor($categoryName)
    {
        $categoryLower = strtolower($categoryName);

        // Mapping intelligent des catégories vers les couleurs
        $mappings = [
            // Matériaux de construction
            'matériaux' => 'badge-brown',
            'construction' => 'badge-brown',
            'béton' => 'badge-gray',
            'ciment' => 'badge-gray',

            // Électricité
            'électri' => 'badge-yellow',
            'electric' => 'badge-yellow',
            'câble' => 'badge-yellow',
            'énergie' => 'badge-yellow',

            // Plomberie
            'plomb' => 'badge-blue',
            'eau' => 'badge-cyan',
            'tuyau' => 'badge-blue',

            // Outils
            'outil' => 'badge-red',
            'équipement' => 'badge-red',

            // Sécurité
            'sécurité' => 'badge-orange',
            'protection' => 'badge-orange',

            // Transport
            'transport' => 'badge-green',
            'logistique' => 'badge-green',
            'véhicule' => 'badge-green',

            // Services
            'service' => 'badge-purple',
            'maintenance' => 'badge-purple',

            // Informatique
            'informatique' => 'badge-indigo',
            'tech' => 'badge-indigo',
            'digital' => 'badge-indigo',

            // Fournitures
            'fourniture' => 'badge-pink',
            'bureau' => 'badge-pink',
            'papeterie' => 'badge-pink',
        ];

        // Rechercher une correspondance
        foreach ($mappings as $keyword => $color) {
            if (strpos($categoryLower, $keyword) !== false) {
                return $color;
            }
        }

        // Couleur par défaut
        return 'badge-blue';
    }
}
