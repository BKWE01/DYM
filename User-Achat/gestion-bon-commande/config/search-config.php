<?php
/**
 * ================================================================
 * CONFIGURATION CENTRALISÉE POUR LA RECHERCHE PAR PRODUIT
 * ================================================================
 * 
 * Ce fichier contient toutes les configurations pour le système
 * de recherche par produit dans les bons de commande.
 * 
 * @author DYM Manufacture
 * @version 1.0.0
 * @created 2025-06-16
 */

// Sécurité : Empêcher l'accès direct
if (!defined('SEARCH_CONFIG_LOADED')) {
    define('SEARCH_CONFIG_LOADED', true);
}

/**
 * ================================================================
 * CONFIGURATION DE LA RECHERCHE
 * ================================================================
 */
class SearchConfig
{
    // Configuration de recherche
    const SEARCH_CONFIG = [
        'min_characters' => 2,
        'max_characters' => 100,
        'search_delay_ms' => 800,
        'max_results' => 50,
        'enable_fuzzy_search' => true,
        'enable_autocomplete' => true
    ];

    // Configuration des tables et colonnes
    const DATABASE_CONFIG = [
        'tables' => [
            'purchase_orders' => 'purchase_orders',
            'achats_materiaux' => 'achats_materiaux',
            'users_exp' => 'users_exp',
            'besoins' => 'besoins',
            'demandeur' => 'demandeur',
            'expression_dym' => 'expression_dym'
        ],
        'columns' => [
            'search_fields' => [
                'am.designation',
                'am.description',
                'am.reference'
            ],
            'order_fields' => [
                'po.order_number',
                'po.fournisseur',
                'po.montant_total',
                'po.generated_at'
            ]
        ]
    ];

    // Messages d'erreur et de succès
    const MESSAGES = [
        'success' => [
            'search_completed' => 'Recherche effectuée avec succès',
            'results_found' => 'bon(s) de commande trouvé(s)',
            'data_loaded' => 'Données chargées avec succès'
        ],
        'errors' => [
            'unauthorized' => 'Utilisateur non connecté',
            'missing_search_term' => 'Terme de recherche manquant',
            'search_too_short' => 'Le terme de recherche doit contenir au moins 2 caractères',
            'search_too_long' => 'Le terme de recherche est trop long',
            'database_error' => 'Erreur lors de la recherche dans la base de données',
            'connection_error' => 'Erreur de connexion à la base de données',
            'system_error' => 'Erreur système lors de la recherche'
        ]
    ];

    // Configuration des scores de pertinence
    const RELEVANCE_SCORES = [
        'exact_match' => 5,
        'starts_with' => 3,
        'contains' => 2,
        'fuzzy_match' => 1
    ];

    /**
     * Obtient la configuration de recherche
     */
    public static function getSearchConfig(): array
    {
        return self::SEARCH_CONFIG;
    }

    /**
     * Obtient la configuration de la base de données
     */
    public static function getDatabaseConfig(): array
    {
        return self::DATABASE_CONFIG;
    }

    /**
     * Obtient un message par clé
     */
    public static function getMessage(string $type, string $key): string
    {
        return self::MESSAGES[$type][$key] ?? "Message non trouvé: {$type}.{$key}";
    }

    /**
     * Obtient les scores de pertinence
     */
    public static function getRelevanceScores(): array
    {
        return self::RELEVANCE_SCORES;
    }

    /**
     * Valide un terme de recherche
     */
    public static function validateSearchTerm(string $term): array
    {
        $term = trim($term);
        $minLength = self::SEARCH_CONFIG['min_characters'];
        $maxLength = self::SEARCH_CONFIG['max_characters'];

        if (empty($term)) {
            return [
                'valid' => false,
                'error' => self::getMessage('errors', 'missing_search_term')
            ];
        }

        if (strlen($term) < $minLength) {
            return [
                'valid' => false,
                'error' => self::getMessage('errors', 'search_too_short')
            ];
        }

        if (strlen($term) > $maxLength) {
            return [
                'valid' => false,
                'error' => self::getMessage('errors', 'search_too_long')
            ];
        }

        return [
            'valid' => true,
            'term' => $term
        ];
    }

    /**
     * Formate une réponse d'API standardisée
     */
    public static function formatResponse(bool $success, string $message, array $data = [], array $additional = []): array
    {
        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        ];

        if ($success) {
            $response['data'] = $data;
            $response = array_merge($response, $additional);
        } else {
            $response['error_code'] = $additional['error_code'] ?? 'GENERAL_ERROR';
            if (isset($additional['debug_info'])) {
                $response['debug_info'] = $additional['debug_info'];
            }
        }

        return $response;
    }

    /**
     * Génère une requête SQL de recherche optimisée
     */
    public static function buildSearchQuery(): string
    {
        return "
            SELECT DISTINCT
                po.id as order_id,
                po.order_number,
                po.download_reference,
                po.expression_id,
                po.related_expressions,
                po.file_path,
                po.fournisseur,
                po.montant_total,
                po.user_id,
                po.is_multi_project,
                po.generated_at,
                po.signature_finance,
                po.user_finance_id,
                u.name as username,
                uf.name as finance_username,
                am.designation as product_found,
                am.quantity as product_quantity,
                am.prix_unitaire as product_unit_price,
                am.unit as product_unit,
                am.reference as product_reference,
                CASE 
                    WHEN po.expression_id LIKE '%EXP_B%' THEN 'besoins' 
                    ELSE 'expression_dym' 
                END as source_table,
                CASE 
                    WHEN po.signature_finance IS NOT NULL AND po.user_finance_id IS NOT NULL 
                    THEN 'validé' 
                    ELSE 'en_attente' 
                END as status,
                CASE 
                    WHEN am.designation = ? THEN " . self::RELEVANCE_SCORES['exact_match'] . "
                    WHEN am.designation LIKE CONCAT(?, '%') THEN " . self::RELEVANCE_SCORES['starts_with'] . "
                    WHEN am.designation LIKE CONCAT('%', ?, '%') THEN " . self::RELEVANCE_SCORES['contains'] . "
                    ELSE " . self::RELEVANCE_SCORES['fuzzy_match'] . "
                END as relevance_score
            FROM purchase_orders po
            LEFT JOIN users_exp u ON po.user_id = u.id
            LEFT JOIN users_exp uf ON po.user_finance_id = uf.id
            INNER JOIN achats_materiaux am ON (
                (po.expression_id = am.expression_id 
                 AND po.fournisseur = am.fournisseur 
                 AND DATE(po.generated_at) = DATE(am.date_achat))
                OR 
                (po.related_expressions IS NOT NULL AND 
                 JSON_SEARCH(po.related_expressions, 'one', am.expression_id) IS NOT NULL AND 
                 po.fournisseur = am.fournisseur AND 
                 DATE(po.generated_at) = DATE(am.date_achat))
            )
            WHERE (
                am.designation LIKE ? 
                OR am.reference LIKE ?
                OR am.description LIKE ?
            )
            ORDER BY relevance_score DESC, po.generated_at DESC
            LIMIT " . self::SEARCH_CONFIG['max_results'] . "
        ";
    }

    /**
     * Prépare les paramètres de recherche pour la requête SQL
     */
    public static function prepareSearchParams(string $searchTerm): array
    {
        $searchPattern = '%' . $searchTerm . '%';
        $startPattern = $searchTerm . '%';
        
        return [
            $searchTerm,      // Correspondance exacte
            $searchTerm,      // Commence par
            $searchTerm,      // Contient
            $searchPattern,   // designation LIKE
            $searchPattern,   // reference LIKE
            $searchPattern    // description LIKE
        ];
    }
}

// Définir la constante pour indiquer que le fichier a été chargé
define('SEARCH_CONFIG_LOADED', true);
?>