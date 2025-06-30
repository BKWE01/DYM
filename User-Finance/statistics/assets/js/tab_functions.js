/**
 * Gestion des onglets pour la page de détails du projet
 */
document.addEventListener('DOMContentLoaded', function () {
    console.log("Script de gestion des onglets chargé");
    
    // Sélectionner tous les boutons d'onglets et les contenus
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    console.log("Nombre de boutons d'onglets:", tabButtons.length);
    console.log("Nombre de contenus d'onglets:", tabContents.length);

    // Fonction pour activer un onglet
    function activateTab(tabId) {
        console.log("Activation de l'onglet:", tabId);
        
        // Désactiver tous les boutons d'onglets
        tabButtons.forEach(btn => {
            // Retirer les classes actives
            btn.classList.remove('active');
            btn.classList.remove('border-blue-500');
            btn.classList.remove('text-blue-600');
            
            // Ajouter les classes inactives
            btn.classList.add('border-transparent');
            btn.classList.add('text-gray-500');
            btn.classList.add('hover:text-gray-700');
            btn.classList.add('hover:border-gray-300');
            
            console.log("Désactivation du bouton:", btn.getAttribute('data-tab'));
        });

        // Cacher tous les contenus d'onglets
        tabContents.forEach(content => {
            content.classList.add('hidden');
            console.log("Contenu caché:", content.id);
        });

        // Activer l'onglet sélectionné
        const selectedTab = document.querySelector(`[data-tab="${tabId}"]`);
        if (selectedTab) {
            // Ajouter les classes actives
            selectedTab.classList.add('active');
            selectedTab.classList.add('border-blue-500');
            selectedTab.classList.add('text-blue-600');
            
            // Retirer les classes inactives
            selectedTab.classList.remove('border-transparent');
            selectedTab.classList.remove('text-gray-500');
            selectedTab.classList.remove('hover:text-gray-700');
            selectedTab.classList.remove('hover:border-gray-300');
            
            console.log("Bouton activé:", tabId);

            // Trouver et afficher le contenu d'onglet correspondant
            const selectedContent = document.getElementById(tabId);
            if (selectedContent) {
                selectedContent.classList.remove('hidden');
                console.log("Contenu affiché:", tabId);
            } else {
                console.error('Contenu d\'onglet non trouvé pour:', tabId);
            }
        } else {
            console.error('Bouton d\'onglet non trouvé pour:', tabId);
        }
    }

    // Ajouter les écouteurs d'événements à chaque bouton
    tabButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            const tabId = this.getAttribute('data-tab');
            console.log('Clic sur l\'onglet:', tabId);
            activateTab(tabId);
            event.preventDefault(); // Empêcher le comportement par défaut
        });
    });

    // Activer l'onglet par défaut (le premier) au chargement de la page
    if (tabButtons.length > 0) {
        // Force l'activation de l'onglet "tab-materials" au chargement
        activateTab('tab-materials');
    }
});