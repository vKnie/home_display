<?php
// Chemin vers le dossier des images
$dir = '../pictures/';
$jsonFile = '../data.json';

// Initialiser la variable pour les images
$images = [];

// Charger les données JSON existantes
if (file_exists($jsonFile)) {
    $jsonData = file_get_contents($jsonFile);
    $imagesData = json_decode($jsonData, true);
} else {
    $imagesData = []; // Si le fichier n'existe pas, initialiser un tableau vide
}

// Vérifier si le dossier des images existe
if (is_dir($dir)) {
    $images = array_diff(scandir($dir), array('.', '..')); // Lister les fichiers en ignorant . et ..
}

// Vérifier si une image doit être supprimée
if (isset($_POST['delete'])) {
    $fileToDelete = $_POST['delete'];
    if (file_exists($dir . $fileToDelete)) {
        unlink($dir . $fileToDelete); // Supprimer le fichier
        unset($imagesData[$fileToDelete]); // Supprimer les données de l'image du tableau
        file_put_contents($jsonFile, json_encode($imagesData, JSON_PRETTY_PRINT)); // Mettre à jour le fichier JSON

        // Redirection pour actualiser la page
        header("Location: list.php?deleted=" . urlencode($fileToDelete));
        exit; // Assurez-vous de sortir du script après la redirection
    }
}

// Vérifier si une image doit être sauvegardée pour le cooldown
if (isset($_POST['filename']) && isset($_POST['cooldown'])) {
    $filename = $_POST['filename'];
    $cooldown = $_POST['cooldown'] ?? "0"; // Valeur par défaut à "0"

    // Mettre à jour ou ajouter les données de l'image
    $imagesData[$filename]['cooldown'] = $cooldown;

    // Sauvegarder les données mises à jour dans le fichier JSON
    file_put_contents($jsonFile, json_encode($imagesData, JSON_PRETTY_PRINT)); 
    header("Location: list.php?updated=" . urlencode($filename));
    exit; // Assurez-vous de sortir du script après la redirection
}

// Traitement AJAX pour le switch
if (isset($_POST['switch']) && isset($_POST['filename'])) {
    $filename = $_POST['filename'];
    $display = $_POST['switch']; // Valeur "1" ou "0"
    
    // Mettre à jour ou ajouter les données de l'image
    if (!isset($imagesData[$filename])) {
        $imagesData[$filename] = []; // Initialiser si ce n'est pas déjà défini
    }
    $imagesData[$filename]['affichage'] = $display;

    // Sauvegarder les données mises à jour dans le fichier JSON
    file_put_contents($jsonFile, json_encode($imagesData, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]); // Répondre avec succès
    exit; // Sortir du script
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afficher les Images</title>
    <link rel="stylesheet" href="list.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* CSS pour le switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #2196F3;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        /* Table styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #444;
        }

        th {
            background-color: #1f1f1f;
        }

        tr:nth-child(even) {
            background-color: #222;
        }

        img {
            width: 100px;
            height: auto;
        }
        /* Styles pour le bouton de sauvegarde */
        .btn-save {
            margin-top: 5px;
            background-color: #4CAF50; /* Vert */
        }

        .btn-save:hover {
            background-color: #45a049; /* Vert foncé au survol */
        }

        /* Styles pour le bouton de suppression */
        .btn-delete {
            background-color: #f44336; /* Rouge */
        }

        .btn-delete:hover {
            background-color: #e53935; /* Rouge foncé au survol */
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            Liste des Images
            <a href="../index.html" style="color: white; margin-left: 10px; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> <!-- Icône à droite -->
            </a>
        </h1>
        <?php if (isset($_GET['deleted'])): ?>
            <p style="color: green;">L'image "<?php echo htmlspecialchars($_GET['deleted']); ?>" a été supprimée.</p>
        <?php elseif (isset($_GET['updated'])): ?>
            <p style="color: green;">Les paramètres de "<?php echo htmlspecialchars($_GET['updated']); ?>" ont été sauvegardés.</p>
        <?php endif; ?>
        <table>
            <thead>
                <tr>
                    <th>Visuel de l'Image</th>
                    <th>Nom du Fichier</th>
                    <th>Afficher</th>
                    <th>Cooldown (en secondes)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($images)): ?>
                    <tr>
                        <td colspan="5">Aucune image trouvée dans le dossier.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($images as $image): ?>
                        <tr>
                            <td><img src="<?php echo $dir . $image; ?>" alt="<?php echo $image; ?>"></td>
                            <td><?php echo $image; ?></td>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" class="display-switch" data-filename="<?php echo $image; ?>" <?php echo (isset($imagesData[$image]) && $imagesData[$image]['affichage'] == "1") ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </td>
                            <td>
                                <form method="POST" class="cooldown-form">
                                    <input type="number" name="cooldown" placeholder="Cooldown" min="0" step="1" value="<?php echo isset($imagesData[$image]) ? $imagesData[$image]['cooldown'] : ''; ?>">
                                    <input type="hidden" name="filename" value="<?php echo $image; ?>">
                                    <button type="submit" class="btn btn-save">Sauvegarder</button>
                                </form>
                            </td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="delete" value="<?php echo $image; ?>">
                                    <button type="submit" class="btn btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette image ?');">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        $(document).ready(function() {
            $('.display-switch').change(function() {
                var filename = $(this).data('filename');
                var switchValue = this.checked ? 1 : 0;

                $.ajax({
                    type: 'POST',
                    url: 'list.php',
                    data: {
                        switch: switchValue,
                        filename: filename
                    },
                    success: function(response) {
                        // Si vous avez besoin d'un traitement de réponse, vous pouvez le faire ici
                        console.log('Switch updated for ' + filename + ': ' + switchValue);
                    },
                    error: function() {
                        alert('Erreur lors de la mise à jour de l\'état du switch.');
                    }
                });
            });
        });
    </script>
</body>
</html>
