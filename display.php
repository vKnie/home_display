<?php
defined('_JEXEC') or die;
use Joomla\CMS\Factory;

$imageFolder = JPATH_ROOT . './img/';
$jsonFile = $imageFolder . 'config.json';

$lastModified = file_exists($jsonFile) ? filemtime($jsonFile) : 0;
$configData = json_decode(file_get_contents($jsonFile), true);
$currentDate = new DateTime();
$imagesToDisplay = [];
$totalDelay = 1;

foreach ($configData as $imageName => $data) {
	if (
		isset($data['show']) && $data['show'] == 1 && 
		isset($data['delay']) && isset($data['order'])
	) {
		$startDate = isset($data['start_date']) && $data['start_date'] !== '' ? new DateTime($data['start_date']) : null;
		$endDate = isset($data['end_date']) && $data['end_date'] !== '' ? new DateTime($data['end_date']) : null;

		if (
			(!$startDate || $currentDate >= $startDate) && 
			(!$endDate || $currentDate <= $endDate)
		) {
			$filePath = $imageFolder . $imageName;
			if (file_exists($filePath)) {
				$imagesToDisplay[] = [
					'name' => $imageName,
					'order' => (int)$data['order'],
					'delay' => (int)$data['delay'],
					'background_color' => $data['background_color'] ?? '#FFFFFF',
					'start_date' => $data['start_date'] ?? '',
					'end_date' => $data['end_date'] ?? ''
				];
				$totalDelay += (int)$data['delay'];
			}
		}
	}
}

usort($imagesToDisplay, function ($a, $b) {
	return $a['order'] <=> $b['order'];
});

$sortedImages = array_map(function($item) {
	return $item['name'];
}, $imagesToDisplay);

$sortedCooldowns = array_map(function($item) {
	return $item['delay'];
}, $imagesToDisplay);

$sortedColors = array_map(function($item) {
	return $item['background_color'];
}, $imagesToDisplay);

if (empty($sortedImages)) {
	die("Erreur : Aucune image valide trouvée dans le dossier.");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="refresh" content="<?php echo $totalDelay; ?>">
	<title>Diaporama d'Images</title>
	<style>
		html, body {
			margin: 0;
			padding: 0;
			overflow: hidden; /* Empêche les barres de défilement */
			height: 100%;
			width: 100%;
		}

		body {
			display: flex;
			justify-content: center;
			align-items: center;
			overflow: hidden; /* Empêche tout débordement du body */
			background-color: #FFFFFF; /* Couleur de fond par défaut */
			transition: background-color 1s ease-in-out;
		}

		img {
			position: fixed; /* Fixe l'image à l'écran */
			top: 50%;
			left: 50%;
			width: 100vw; /* Prend 100% de la largeur de la fenêtre */
			height: 100vh; /* Prend 100% de la hauteur de la fenêtre */
			object-fit: contain; /* Garder les proportions de l'image */
			transform: translate(-50%, -50%);
			transition: opacity 1s ease-in-out; /* Transition pour le fondu */
			opacity: 0;
		}

		.visible {
			opacity: 1;
		}

		.fade-out {
			opacity: 0;
		}
		 .article-header, #sp-footer, .full-header {
			visibility: hidden;
		}
	</style>
</head>
<body>
	<img id="full-screen-image" src="<?php echo JUri::root() . 'img/' . htmlspecialchars($sortedImages[0]); ?>" alt="Image en plein écran">

	<script>
		document.documentElement.style.overflow = 'hidden';
		document.body.style.overflow = 'hidden';

		let images = <?php echo json_encode($sortedImages); ?>;
		let cooldowns = <?php echo json_encode($sortedCooldowns); ?>;
		let colors = <?php echo json_encode($sortedColors); ?>;
		let currentIndex = 0;
		let intervalId;

		// Fonction pour changer l'image avec un effet de fondu
		function changeImage() {
			const currentImage = document.getElementById('full-screen-image');
			const nextIndex = (currentIndex + 1) % images.length;

			// Appliquer le fondu sortant à l'image actuelle
			currentImage.classList.remove('visible');
			currentImage.classList.add('fade-out');

			// Changer la couleur de fond
			document.body.style.backgroundColor = colors[nextIndex];

			// Charger l'image suivante
			const nextImageSrc = '<?php echo JUri::root(); ?>img/' + images[nextIndex];
			const nextImage = new Image();
			nextImage.src = nextImageSrc;

			// Quand la nouvelle image est chargée
			nextImage.onload = () => {
				// Attendre que l'image précédente ait complètement disparu
				setTimeout(() => {
					currentImage.src = nextImageSrc; // Mettre à jour l'image
					currentImage.classList.remove('fade-out'); // Retirer la classe de fondu sortant
					currentImage.classList.add('visible'); // Appliquer le fondu entrant pour la nouvelle image
				}, 1000); // Attendre la fin du fondu sortant avant de changer l'image
			};

			currentIndex = nextIndex;

			const nextCooldown = cooldowns[currentIndex] * 1000;
			intervalId = setTimeout(changeImage, nextCooldown); // Délai avant le prochain changement d'image
		}

		// Fonction pour démarrer le diaporama
		function startSlideshow() {
			document.getElementById('full-screen-image').classList.add('visible'); // Affiche l'image initiale
			document.body.style.backgroundColor = colors[currentIndex]; // Appliquer la couleur de fond initiale
			const initialCooldown = cooldowns[currentIndex] * 1000;
			setTimeout(() => {
				changeImage();
			}, initialCooldown); // Attendre le délai initial avant de commencer
		}

		startSlideshow();
	</script>
</body>
</html>