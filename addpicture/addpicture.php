<?php
// Dossier où les images seront enregistrées
$targetDir = "../pictures/";
$targetFile = $targetDir . basename($_FILES["image"]["name"]);
$uploadOk = 1;
$imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
$jsonFile = '../data.json';

// Charger les données JSON existantes
if (file_exists($jsonFile)) {
    $jsonData = file_get_contents($jsonFile);
    $imagesData = json_decode($jsonData, true);
} else {
    $imagesData = []; // Si le fichier n'existe pas, initialiser un tableau vide
}

// Vérifier si l'image est une image réelle ou un faux
if (isset($_POST["submit"])) {
    $check = getimagesize($_FILES["image"]["tmp_name"]);
    if ($check !== false) {
        echo "Le fichier est une image - " . $check["mime"] . ".";
        $uploadOk = 1;
    } else {
        echo "Ce n'est pas une image.";
        $uploadOk = 0;
    }
}

// Vérifier si le fichier existe déjà
if (file_exists($targetFile)) {
    echo "Désolé, le fichier existe déjà.";
    $uploadOk = 0;
}

// Vérifier la taille du fichier (max 5 Mo)
if ($_FILES["image"]["size"] > 5000000) {
    echo "Désolé, votre fichier est trop volumineux.";
    $uploadOk = 0;
}

// Autoriser certains formats de fichier
if (!in_array($imageFileType, ["jpg", "jpeg", "png"])) {
    echo "Désolé, seuls les fichiers JPG, JPEG et PNG sont autorisés.";
    $uploadOk = 0;
}

// Vérifier si $uploadOk est à 0 à cause d'une erreur
if ($uploadOk == 0) {
    echo "Désolé, votre fichier n'a pas été téléchargé.";
// Si tout est ok, essayer de télécharger le fichier
} else {
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
        // Ajouter les valeurs par défaut au JSON
        $filename = htmlspecialchars(basename($_FILES["image"]["name"]));
        if (!isset($imagesData[$filename])) {
            $imagesData[$filename] = [
                'affichage' => "0",  // Off par défaut
                'cooldown' => "1"    // Cooldown de 1 seconde par défaut
            ];
            file_put_contents($jsonFile, json_encode($imagesData, JSON_PRETTY_PRINT)); // Mettre à jour le fichier JSON
        }
        
        // Redirection vers une page HTML après le téléchargement réussi
        header("Location: ../list/list.php"); // Remplacez "success.html" par le nom de votre fichier HTML
        exit(); // Assurez-vous de quitter le script après la redirection
    } else {
        echo "Désolé, une erreur est survenue lors du téléchargement de votre fichier.";
    }
}
?>
