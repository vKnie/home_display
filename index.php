<?php
defined('_JEXEC') or die;
use Joomla\CMS\Factory;

$imageFolder = JPATH_ROOT . './img/';
$jsonFile = $imageFolder . 'config.json';

if (!file_exists($imageFolder)) {
	mkdir($imageFolder, 0755, true);
}
if (!file_exists($jsonFile)) {
	file_put_contents($jsonFile, json_encode([]));
}

$configData = json_decode(file_get_contents($jsonFile), true);

if (!is_array($configData)) {
	$configData = [];
}

$app = Factory::getApplication();

function getDominantColor($imagePath) {
	if (!file_exists($imagePath)) {
		return '#FFFFFF'; // Retourne blanc si l'image n'existe pas
	}

	$imageInfo = getimagesize($imagePath);
	$imageType = $imageInfo[2];

	switch ($imageType) {
		case IMAGETYPE_JPEG:
			$image = imagecreatefromjpeg($imagePath);
			break;
		case IMAGETYPE_PNG:
			$image = imagecreatefrompng($imagePath);
			break;
		case IMAGETYPE_GIF:
			$image = imagecreatefromgif($imagePath);
			break;
		default:
			return '#FFFFFF'; // Retourne blanc si le type d'image n'est pas supporté
	}

	$resizedImage = imagecreatetruecolor(1, 1);
	imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));

	$rgb = imagecolorat($resizedImage, 0, 0);
	$colors = imagecolorsforindex($resizedImage, $rgb);

	imagedestroy($image);
	imagedestroy($resizedImage);

	return sprintf('#%02x%02x%02x', $colors['red'], $colors['green'], $colors['blue']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
	$file = $_FILES['file'];
	$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

	if ($file['error'] !== UPLOAD_ERR_OK) {
		$app->enqueueMessage('Erreur : Impossible d\'uploader le fichier. Code d\'erreur: ' . $file['error'], 'error');
	} else {
		$imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
		if (in_array($fileExtension, $imageExtensions)) {
			if ($fileExtension === 'pdf') {
				$imagick = new Imagick();
				$imagick->setResolution(300, 300);
				$imagick->readImage($file['tmp_name']);
				foreach ($imagick as $index => $page) {
					$imagePath = $imageFolder . basename($file['name'], '.pdf') . "-page-$index.jpg";
					$page->setImageFormat('jpg');
					$page->writeImage($imagePath);
					$dominantColor = getDominantColor($imagePath);
					$configData[basename($file['name'], '.pdf') . "-page-$index.jpg"] = [
						"show" => 0,
						"delay" => 5,
						"order" => 0,
						"start_date" => '',
						"end_date" => '',
						"background_color" => $dominantColor
					];
				}
				$imagick->clear();
				$imagick->destroy();
			} else {
				$targetPath = $imageFolder . basename($file['name']);
				move_uploaded_file($file['tmp_name'], $targetPath);
				$dominantColor = getDominantColor($targetPath);
				$configData[$file['name']] = [
					"show" => 0,
					"delay" => 5,
					"order" => 0,
					"start_date" => '',
					"end_date" => '',
					"background_color" => $dominantColor
				];
			}
			file_put_contents($jsonFile, json_encode($configData, JSON_PRETTY_PRINT));
			$app->enqueueMessage('Fichier uploadé avec succès !', 'message');
		} else {
			$app->enqueueMessage('Erreur : Type de fichier non autorisé.', 'error');
		}
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
	foreach ($_POST['images'] as $imageName => $settings) {
		if (isset($configData[$imageName])) {
			$configData[$imageName]['show'] = isset($settings['show']) ? (int)$settings['show'] : 0;
			$configData[$imageName]['delay'] = isset($settings['delay']) ? (int)$settings['delay'] : 5;
			$configData[$imageName]['order'] = isset($settings['order']) ? (int)$settings['order'] : 0;
			$configData[$imageName]['start_date'] = isset($settings['start_date']) ? $settings['start_date'] : '';
			$configData[$imageName]['end_date'] = isset($settings['end_date']) ? $settings['end_date'] : '';
			$configData[$imageName]['background_color'] = isset($settings['background_color']) ? $settings['background_color'] : '#FFFFFF';
		}
	}
	file_put_contents($jsonFile, json_encode($configData, JSON_PRETTY_PRINT));
	$app->enqueueMessage('Configurations mises à jour avec succès !', 'message');
}

if (isset($_POST['delete'])) {
	$fileName = $_POST['delete'];
	$filePath = $imageFolder . $fileName;

	if (file_exists($filePath)) {
		unlink($filePath);
		unset($configData[$fileName]);
		file_put_contents($jsonFile, json_encode($configData, JSON_PRETTY_PRINT));
		$app->enqueueMessage('Fichier supprimé avec succès !', 'message');
	} else {
		$app->enqueueMessage('Erreur : Fichier introuvable.', 'error');
	}
}

$files = array_diff(scandir($imageFolder), ['..', '.', 'config.json']);

foreach ($files as $file) {
	if (!isset($configData[$file])) {
		$dominantColor = getDominantColor($imageFolder . $file);
		$configData[$file] = [
			"show" => 0,
			"delay" => 5,
			"order" => 0,
			"start_date" => '',
			"end_date" => '',
			"background_color" => $dominantColor
		];
	}
}
?>

<div class="image-uploader">
	<h2>Uploader une image (Formats acceptés: PDF, PNG, JPEG)</h2>
	<form method="POST" enctype="multipart/form-data">
		<input type="file" name="file" accept="image/*" required>
		<button type="submit" class="btn-upload">Uploader</button>
	</form>

	<h2>Liste des fichiers</h2>
	<form method="POST">
		<table>
			<thead>
				<tr>
					<th>Ordre</th>
					<th>Nom du fichier</th>
					<th>Image</th>
					<th>Afficher</th>
					<th>Délai (s)</th>
					<th>Date début</th>
					<th>Date fin</th>
					<th>Couleur de fond</th>
					<th>Supprimer</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($files as $file): ?>
					<tr>
						<td><input type="number" name="images[<?php echo htmlspecialchars($file); ?>][order]" value="<?php echo htmlspecialchars($configData[$file]['order']); ?>" min="0"></td>
						<td><?php echo htmlspecialchars($file); ?></td>
						<td>
							<img src="<?php echo JUri::root() . 'img/' . htmlspecialchars($file); ?>" alt="<?php echo htmlspecialchars($file); ?>" style="max-width:120px;">
						</td>
						<td>
							<input type="checkbox" name="images[<?php echo htmlspecialchars($file); ?>][show]" value="1" <?php echo (!empty($configData[$file]['show']) && $configData[$file]['show'] == 1) ? 'checked' : ''; ?>>
						</td>
						<td>
							<input type="number" name="images[<?php echo htmlspecialchars($file); ?>][delay]" value="<?php echo isset($configData[$file]['delay']) ? htmlspecialchars($configData[$file]['delay']) : 5; ?>" min="1">
						</td>
						<td>
							<input type="date" name="images[<?php echo htmlspecialchars($file); ?>][start_date]" value="<?php echo htmlspecialchars($configData[$file]['start_date']); ?>">
						</td>
						<td>
							<input type="date" name="images[<?php echo htmlspecialchars($file); ?>][end_date]" value="<?php echo htmlspecialchars($configData[$file]['end_date']); ?>">
						</td>
						<td>
							<input type="color" name="images[<?php echo htmlspecialchars($file); ?>][background_color]" value="<?php echo htmlspecialchars($configData[$file]['background_color']); ?>">
						</td>
						<td>
							<button type="submit" name="delete" value="<?php echo htmlspecialchars($file); ?>" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce fichier ?');" class="btn-delete">Supprimer</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<button type="submit" name="update" class="btn-update">Mettre à jour</button>
		<a href="index.php" class="btn-display">Affichage !</a>
	</form>
</div>

<style>
	/* Formulaire d'upload */
	.image-uploader form {
		margin-bottom: 20px;
		display: flex;
		flex-direction: column;
		align-items: center;
	}

	.image-uploader input[type="file"] {
		margin-bottom: 10px;
		padding: 10px;
		border-radius: 5px;
		border: 1px solid #ddd;
		width: 80%;
	}

	/* Style du tableau */
	.image-uploader table {
		width: 100%;
		border-collapse: collapse;
		margin-top: 20px;
	}

	.image-uploader table, .image-uploader th, .image-uploader td {
		border: 1px solid #ddd;
		padding: 12px;
		text-align: center;
	}

	.image-uploader th {
		background-color: #f2f2f2;
		font-weight: bold;
	}

	.image-uploader td {
		background-color: #fff;
	}

	/* Boutons */
	.btn-upload, .btn-delete, .btn-update, .btn-display {
		border: none;
		border-radius: 5px;
		padding: 10px 15px;
		font-size: 14px;
		cursor: pointer;
		transition: background-color 0.3s ease;
	}

	/* Bouton d'upload */
	.btn-upload {
		background-color: #4CAF50;
		color: white;
	}

	.btn-upload:hover {
		background-color: #45a049;
	}

	/* Bouton de suppression */
	.btn-delete {
		background-color: #f44336;
		color: white;
	}

	.btn-delete:hover {
		background-color: #e53935;
	}

	/* Bouton de mise à jour */
	.btn-update {
		background-color: #2196F3;
		color: white;
	}

	.btn-update:hover {
		background-color: #1e88e5;
	}

	/* Bouton d'affichage */
	.btn-display {
		background-color: #4CAF50;
		color: white;
		text-decoration: none;
	}

	.btn-display:hover {
		background-color: #45a049;
	}

	/* Changement de couleur sur les champs du formulaire au survol */
	.image-uploader input[type="number"], .image-uploader input[type="date"], .image-uploader input[type="checkbox"] {
		width: 100%;
		border-radius: 5px;
		padding: 8px;
		border: 1px solid #ddd;
	}

	/* Pour un meilleur espacement des éléments */
	.image-uploader h2 {
		text-align: center;
		color: #333;
		margin-bottom: 20px;
	}

	/* Améliorer l'image dans le tableau */
	.image-uploader img {
		max-width: 100px;
		max-height: 100px;
		border-radius: 5px;
	}

	/* Ajouter un effet de survol sur les lignes du tableau */
	.image-uploader tbody tr:hover {
		background-color: #f1f1f1;
	}
</style>