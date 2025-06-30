<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance | Expressions Système</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: #334155;
        }

        .maintenance-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            max-width: 800px;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .rotate {
            animation: rotate 3s linear infinite;
        }

        @keyframes rotate {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        .progress-container {
            width: 100%;
            background-color: #e2e8f0;
            border-radius: 9999px;
            overflow: hidden;
            height: 8px;
        }

        .progress-bar {
            height: 100%;
            background-color: #3b82f6;
            border-radius: 9999px;
            width: 35%;
            animation: progress 1.5s ease-in-out infinite alternate;
        }

        @keyframes progress {
            from {
                width: 35%;
            }
            to {
                width: 75%;
            }
        }

        #countdown {
            font-variant-numeric: tabular-nums;
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4">
    <div class="maintenance-card p-8 w-full">
        <div class="flex flex-col items-center text-center">
            <!-- Logo/Header -->
            <div class="mb-6">
                <!-- Logo de l'entreprise -->
                <div class="flex justify-center mb-4">
                    <img src="public/logo.png" alt="Logo Entreprise" class="h-20">
                    <!-- Note: Remplacez 'path/to/your/logo.png' par le chemin réel vers votre logo -->
                </div>
                <h1 class="text-3xl font-bold text-gray-800 flex items-center justify-center">
                    <span class="material-icons mr-2 text-blue-500" style="font-size: 36px;">
                        business
                    </span>
                    Expressions Système
                </h1>
            </div>

            <!-- Status Badge -->
            <div class="mb-8">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-amber-100 text-amber-800">
                    <span class="material-icons mr-1 text-amber-500 pulse" style="font-size: 18px;">
                        engineering
                    </span>
                    Maintenance en cours
                </span>
            </div>

            <!-- Main Icon -->
            <div class="mb-8 text-blue-500">
                <div class="relative">
                    <span class="material-icons rotate" style="font-size: 120px;">settings</span>
                    <span class="material-icons absolute top-9 left-9" style="font-size: 60px; opacity: 0.7;">build</span>
                </div>
            </div>

            <!-- Message -->
            <div class="mb-8 max-w-lg">
                <h2 class="text-2xl font-bold text-gray-700 mb-4">Nous effectuons une maintenance planifiée</h2>
                <p class="text-gray-600 mb-3">
                    Notre équipe technique travaille actuellement sur des améliorations pour vous offrir une meilleure expérience.
                    Le système sera de nouveau disponible très prochainement.
                </p>
                <p class="text-gray-600 font-medium">
                    Nous vous remercions pour votre patience et compréhension.
                </p>
            </div>

            <!-- Progress -->
            <div class="mb-6 w-full max-w-md">
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>Progression estimée</span>
                    <span>35%</span>
                </div>
                <div class="progress-container">
                    <div class="progress-bar"></div>
                </div>
            </div>

            <!-- Countdown -->
            <div class="mb-8">
                <p class="text-gray-600 mb-2">Temps estimé avant rétablissement:</p>
                <div id="countdown" class="text-3xl font-bold text-blue-600">02:30:00</div>
            </div>

            <!-- Contact -->
            <div class="border-t border-gray-200 pt-6 mt-2">
                <p class="text-gray-600 mb-3">Pour toute urgence, veuillez contacter:</p>
                <div class="flex flex-col sm:flex-row justify-center gap-4 mb-8">
                    <a href="mailto:support@expression-systeme.com" class="flex items-center justify-center px-4 py-2 border border-blue-300 rounded-md text-blue-600 bg-blue-50 hover:bg-blue-100 transition-colors">
                        <span class="material-icons mr-2" style="font-size: 18px;">email</span>
                        support@expression-systeme.com
                    </a>
                    <a href="tel:+2250767376920" class="flex items-center justify-center px-4 py-2 border border-green-300 rounded-md text-green-600 bg-green-50 hover:bg-green-100 transition-colors">
                        <span class="material-icons mr-2" style="font-size: 18px;">phone</span>
                        +225 07 67 37 69 20
                    </a>
                </div>
                
                <!-- Bouton de retour -->
                <a href="index.php" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-md transition-all duration-200">
                    <span class="material-icons mr-2" style="font-size: 18px;">arrow_back</span>
                    Retour à l'accueil
                </a>
            </div>
        </div>
    </div>

    <script>
        // Countdown Timer
        function updateCountdown() {
            const countdownEl = document.getElementById('countdown');
            let parts = countdownEl.textContent.split(':');
            let hours = parseInt(parts[0], 10);
            let minutes = parseInt(parts[1], 10);
            let seconds = parseInt(parts[2], 10);

            seconds--;

            if (seconds < 0) {
                seconds = 59;
                minutes--;
                
                if (minutes < 0) {
                    minutes = 59;
                    hours--;
                    
                    if (hours < 0) {
                        // Timer expired
                        countdownEl.textContent = "00:00:00";
                        return;
                    }
                }
            }

            // Format time with leading zeros
            hours = hours.toString().padStart(2, '0');
            minutes = minutes.toString().padStart(2, '0');
            seconds = seconds.toString().padStart(2, '0');

            countdownEl.textContent = `${hours}:${minutes}:${seconds}`;
        }

        // Update every second
        setInterval(updateCountdown, 1000);
    </script>
</body>

</html>