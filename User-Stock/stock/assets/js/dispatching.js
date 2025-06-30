/**
 * Module de gestion du dispatching des entrées de produits
 */
const DispatchingManager = {
    /**
     * Traite les résultats de dispatching et affiche les notifications appropriées
     * @param {Array} dispatchingResults - Les résultats du dispatching
     */
    handleDispatchingResults: function (dispatchingResults) {
        if (!dispatchingResults || dispatchingResults.length === 0) {
            return;
        }

        // Créer un résumé des résultats
        const completedOrders = dispatchingResults.filter(r => r.status === 'completed');
        const partialOrders = dispatchingResults.filter(r => r.status === 'partial');

        // Si aucun ordre n'a été traité, ne rien afficher
        if (completedOrders.length === 0 && partialOrders.length === 0) {
            return;
        }

        // Générer un message détaillé pour la modal
        let detailedContent = '<div class="mt-4 max-h-96 overflow-y-auto">';

        if (completedOrders.length > 0) {
            detailedContent += '<h4 class="text-lg font-semibold text-green-700 mb-2">Commandes complètement satisfaites</h4>';
            detailedContent += '<div class="bg-green-50 p-3 rounded-md mb-4">';
            detailedContent += '<ul class="list-disc pl-5 space-y-1">';

            completedOrders.forEach(order => {
                detailedContent += `
                    <li>
                        <span class="font-medium">${order.product_name}</span>
                        <span class="text-sm"> - ${order.allocated} unités attribuées au projet </span>
                        <span class="text-sm font-medium">${order.project}</span>
                        <span class="text-sm"> (${order.client})</span>
                    </li>
                `;
            });

            detailedContent += '</ul></div>';
        }

        if (partialOrders.length > 0) {
            detailedContent += '<h4 class="text-lg font-semibold text-orange-600 mb-2">Commandes partiellement satisfaites</h4>';
            detailedContent += '<div class="bg-orange-50 p-3 rounded-md">';
            detailedContent += '<ul class="list-disc pl-5 space-y-1">';

            partialOrders.forEach(order => {
                detailedContent += `
                    <li>
                        <span class="font-medium">${order.product_name}</span>
                        <span class="text-sm"> - ${order.allocated} unités attribuées au projet </span>
                        <span class="text-sm font-medium">${order.project}</span>
                        <span class="text-sm"> (${order.client})</span>
                        <span class="text-sm text-orange-700"> - Reste à livrer: ${order.remaining} unités</span>
                    </li>
                `;
            });

            detailedContent += '</ul></div>';
        }

        detailedContent += '</div>';

        // Afficher la modal de résumé
        Swal.fire({
            title: 'Dispatching automatique effectué',
            html: `
                <div class="text-left">
                    <p class="mb-3">L'entrée de stock a été effectuée avec succès. Le système a automatiquement dispatché les produits vers les commandes en attente :</p>
                    <div class="flex items-center justify-between text-sm font-medium bg-gray-100 p-2 rounded-md mb-3">
                        <span>${completedOrders.length} commandes complètement satisfaites</span>
                        <span>${partialOrders.length} commandes partiellement satisfaites</span>
                    </div>
                    ${detailedContent}
                </div>
            `,
            icon: 'success',
            confirmButtonText: 'OK',
            confirmButtonColor: '#3085d6',
            width: '600px'
        }).then((result) => {
            // Rafraîchir la page pour afficher les modifications
            if (result.isConfirmed) {
                window.location.reload();
            }
        });
    }
};

/**
 * Extension jQuery pour ajouter la fonctionnalité de dispatching au formulaire d'entrée de stock
 */
(function ($) {
    $.fn.enableDispatching = function () {
        return this.each(function () {
            const form = $(this);

            // Intercepter la soumission du formulaire
            form.on('submit', function (e) {
                e.preventDefault();

                // Récupérer les entrées
                const entries = [];
                $('#productList > div').each(function () {
                    const div = $(this);
                    entries.push({
                        product_id: div.find('input[name="product_id"]').val(),
                        quantity: parseInt(div.find('input[name="quantity"]').val(), 10),
                        provenance: div.find('input[name="provenance"]').val(),
                        nom_projet: div.find('input[name="nom_projet"]').val() || ''
                    });
                });

                if (entries.length === 0) {
                    notification.warning({
                        message: 'Aucun produit ajouté',
                        description: 'Veuillez ajouter au moins un produit avant de soumettre les entrées.',
                    });
                    return;
                }

                // Vérifier si tous les champs de provenance sont remplis
                const emptyProvenances = entries.filter(entry => !entry.provenance.trim());
                if (emptyProvenances.length > 0) {
                    notification.warning({
                        message: 'Provenance manquante',
                        description: 'Veuillez remplir la provenance pour tous les produits.',
                    });
                    return;
                }

                // Afficher un indicateur de chargement
                Swal.fire({
                    title: 'Traitement en cours...',
                    text: 'Veuillez patienter pendant que le système dispatch les produits',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Envoyer les données au serveur
                fetch('api_addEntries.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ entries: entries }),
                })
                    .then(response => response.json())
                    .then(data => {
                        Swal.close();

                        if (data.success) {
                            // Traiter les résultats du dispatching
                            if (data.dispatching && data.dispatching.length > 0) {
                                DispatchingManager.handleDispatchingResults(data.dispatching);
                            } else {
                                // Notification simple sans dispatching
                                notification.success({
                                    message: 'Entrées ajoutées',
                                    description: data.message,
                                });
                                // Vider la liste des produits après l'ajout réussi
                                $('#productList').html('');
                            }
                        } else {
                            notification.error({
                                message: 'Erreur',
                                description: data.message,
                            });
                        }
                    })
                    .catch(error => {
                        Swal.close();
                        console.error('Erreur:', error);
                        notification.error({
                            message: 'Erreur',
                            description: 'Une erreur est survenue lors de l\'ajout des entrées.',
                        });
                    });
            });
        });
    };
})(jQuery);

// Initialisation du dispatching lorsque la page est chargée
document.addEventListener('DOMContentLoaded', function () {
    if (jQuery && jQuery('#submitEntries').length) {
        jQuery('#submitEntries').closest('form').enableDispatching();
    }
});