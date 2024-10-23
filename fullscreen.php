<?php
// Chemin vers le dossier des images
$dir = 'pictures/'; // Chemin relatif pour le dossier images
$jsonFile = 'data.json'; // Chemin relatif pour le fichier JSON

// Charger les données JSON existantes
$imagesData = [];
if (file_exists($jsonFile)) {
    $jsonData = file_get_contents($jsonFile);
    $imagesData = json_decode($jsonData, true);
} else {
    die("Erreur : Le fichier data.json n'a pas été trouvé.");
}

// Récupérer les images à afficher et leurs cooldowns
$imagesToDisplay = [];
$cooldowns = [];

foreach ($imagesData as $imageName => $data) {
    // Vérifie si l'image doit être affichée
    if (isset($data['affichage']) && $data['affichage'] == "1") {
        // Vérifie si le fichier d'image existe dans le dossier
        if (file_exists($dir . $imageName)) {
            $imagesToDisplay[] = $imageName;
            $cooldowns[] = (int)$data['cooldown']; // Stocker le cooldown comme entier
        }
    }
}

// Vérifier si des images valides ont été trouvées
if (empty($imagesToDisplay)) {
    die("Erreur : Aucune image valide trouvée dans le dossier.");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Images en Plein Écran</title>
    <style>
        body {
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            overflow: hidden; /* Empêche le défilement */
            background: white; /* Couleur de fond blanche */
        }
        img {
            width: 100%; /* S'ajuste à la largeur de la fenêtre */
            height: 100%; /* S'ajuste à la hauteur de la fenêtre */
            object-fit: contain; /* Ajuste l'image sans la déformer */
            transition: opacity 1s; /* Animation de transition pour un effet doux */
        }
    </style>
</head>
<body>
    <img id="full-screen-image" src="<?php echo $dir . $imagesToDisplay[0]; ?>" alt="Image en plein écran">
    
    <script>
        const images = <?php echo json_encode($imagesToDisplay); ?>; // Récupérer uniquement les noms des images
        const cooldowns = <?php echo json_encode($cooldowns); ?>; // Récupérer les cooldowns

        let currentIndex = 0;

        function changeImage() {
            // Changer l'image en utilisant l'indice courant
            const currentImage = document.getElementById('full-screen-image');
            const nextIndex = (currentIndex + 1) % images.length;

            // Animation de transition
            currentImage.style.opacity = 0; // Rendre l'image actuelle transparente

            setTimeout(() => {
                currentImage.src = '<?php echo $dir; ?>' + images[nextIndex]; // Mettre à jour l'image
                currentImage.style.opacity = 1; // Rendre l'image suivante opaque
            }, 1000); // Délai de 1 seconde pour la transition

            // Passer à l'image suivante
            currentIndex = nextIndex;

            // Ajuster le délai suivant basé sur le cooldown de l'image actuelle
            const nextCooldown = cooldowns[currentIndex] * 1000; // Convertir en millisecondes
            setTimeout(changeImage, nextCooldown);
        }

        // Démarrer le changement d'image
        const initialCooldown = cooldowns[currentIndex] * 1000; // Convertir en millisecondes
        setTimeout(changeImage, initialCooldown);
    </script>
</body>
</html>
