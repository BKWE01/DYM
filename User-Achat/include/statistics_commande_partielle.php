<!-- Statistiques des commandes partielles -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <!-- Statistique 1: Matériaux partiellement commandés -->
    <div class="bg-white rounded-lg shadow-sm p-6 card-partial">
        <div class="flex items-center justify-between mb-4">
            <div class="rounded-full bg-yellow-100 p-3">
                <span class="material-icons text-yellow-600">content_paste</span>
            </div>
            <span class="text-yellow-600 text-sm font-medium">Total</span>
        </div>
        <div class="flex flex-col">
            <span class="text-gray-600 text-sm">Matériaux à compléter</span>
            <span class="text-3xl font-bold text-gray-900" id="stat-total-partial">0</span>
            <span class="text-sm text-gray-500 mt-1">commandes partielles</span>
        </div>
    </div>

    <!-- Statistique 2: Quantité totale restante -->
    <div class="bg-white rounded-lg shadow-sm p-6 card-partial">
        <div class="flex items-center justify-between mb-4">
            <div class="rounded-full bg-blue-100 p-3">
                <span class="material-icons text-blue-600">compare_arrows</span>
            </div>
            <span class="text-blue-600 text-sm font-medium">Restant</span>
        </div>
        <div class="flex flex-col">
            <span class="text-gray-600 text-sm">Quantité restante</span>
            <span class="text-3xl font-bold text-gray-900" id="stat-remaining-qty">0</span>
            <span class="text-sm text-gray-500 mt-1">unités à commander</span>
        </div>
    </div>

    <!-- Statistique 3: Projets concernés -->
    <div class="bg-white rounded-lg shadow-sm p-6 card-partial">
        <div class="flex items-center justify-between mb-4">
            <div class="rounded-full bg-purple-100 p-3">
                <span class="material-icons text-purple-600">business</span>
            </div>
            <span class="text-purple-600 text-sm font-medium">Projets</span>
        </div>
        <div class="flex flex-col">
            <span class="text-gray-600 text-sm">Projets concernés</span>
            <span class="text-3xl font-bold text-gray-900" id="stat-projects-count">0</span>
            <span class="text-sm text-gray-500 mt-1">avec commandes partielles</span>
        </div>
    </div>

    <!-- Statistique 4: Progression globale -->
    <div class="bg-white rounded-lg shadow-sm p-6 card-partial">
        <div class="flex items-center justify-between mb-4">
            <div class="rounded-full bg-green-100 p-3">
                <span class="material-icons text-green-600">trending_up</span>
            </div>
            <span class="text-green-600 text-sm font-medium">Progression</span>
        </div>
        <div class="flex flex-col">
            <span class="text-gray-600 text-sm">Avancement global</span>
            <span class="text-3xl font-bold text-gray-900" id="stat-progress">0%</span>
            <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                <div class="bg-green-500 h-2.5 rounded-full" id="progress-bar" style="width: 0%">
                </div>
            </div>
        </div>
    </div>
</div>