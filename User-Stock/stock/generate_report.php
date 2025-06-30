<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/antd@4.16.13/dist/antd.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/react@17.0.2/umd/react.development.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/react-dom@17.0.2/umd/react-dom.development.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/antd@4.16.13/dist/antd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@babel/standalone@7.14.7/babel.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include_once 'sidebar.php'; ?>

        <!-- Main content -->
        <div id="main-content" class="flex-1 flex flex-col">
            <?php include_once 'header.php'; ?>

            <main class="p-4 flex-1">
                <div class="bg-white p-4 rounded-lg shadow">
                    <h1 class="text-3xl font-bold mb-6">Rapport détaillé</h1>
                    <p>Cette page c'est pour généré un rapport</p>
                    
                </div>
            </main>
        </div>
    </div>


</body>
</html>
