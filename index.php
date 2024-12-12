<?php
defined('_JEXEC') or die;
use Joomla\CMS\Factory;

$imageFolder = JPATH_ROOT . '/img/';
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
		return '#FFFFFF';
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
			return '#FFFFFF';
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
		<table id="sortableTable">
			<thead>
				<tr>
					<th data-sort="number">Ordre</th>
					<th>Nom du fichier</th>
					<th>Image</th>
					<th>Afficher</th>
					<th>Délai (s)</th>
					<th data-sort="date">Date début</th>
					<th data-sort="date">Date fin</th>
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
							<?php 
							$endDate = $configData[$file]['end_date'];
							$currentDate = date('Y-m-d');
							
							$style = ($endDate === $currentDate) ? 'color: red;' : '';
							?>
							<input type="date" name="images[<?php echo htmlspecialchars($file); ?>][end_date]" value="<?php echo htmlspecialchars($endDate); ?>" style="<?php echo $style; ?>">
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
	</form>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function() {
		const table = document.getElementById('sortableTable');
		const headers = table.querySelectorAll('th[data-sort]');
		const rows = table.querySelector('tbody').rows;

		headers.forEach(header => {
			header.addEventListener('click', () => {
				const sortType = header.getAttribute('data-sort');
				const columnIndex = Array.from(header.parentNode.children).indexOf(header);
				const isDescending = header.classList.contains('asc');

				headers.forEach(h => h.classList.remove('asc', 'desc'));

				header.classList.add(isDescending ? 'desc' : 'asc');

				const sortedRows = Array.from(rows).sort((a, b) => {
					const cellA = a.cells[columnIndex].querySelector('input')?.value || a.cells[columnIndex].innerText;
					const cellB = b.cells[columnIndex].querySelector('input')?.value || b.cells[columnIndex].innerText;

					if (sortType === 'number') {
						return isDescending ? parseFloat(cellB) - parseFloat(cellA) : parseFloat(cellA) - parseFloat(cellB);
					} else if (sortType === 'date') {
						return isDescending ? new Date(cellB) - new Date(cellA) : new Date(cellA) - new Date(cellB);
					} else {
						return isDescending ? cellB.localeCompare(cellA) : cellA.localeCompare(cellB);
					}
				});

				const tbody = table.querySelector('tbody');
				tbody.innerHTML = '';
				sortedRows.forEach(row => tbody.appendChild(row));
			});
		});
	});
</script>

<style>
	.image-uploader form {
		margin-bottom: 20px;
		display: flex;
		flex-direction: column;
		align-items: center;
	}

	/* Zone d'upload avant qu'un fichier ne soit sélectionné */
	.image-uploader input[type="file"] {
		margin-bottom: 10px;
		padding: 12px 15px;
		border-radius: 8px;
		border: 1px solid #ddd;
		width: 80%;
		font-size: 14px;
		background-color: #e8f5e9; /* Fond léger vert clair avant l'upload */
	}

	/* Bordure verte quand un fichier est sélectionné */
	.image-uploader input[type="file"]:valid {
		border-color: #45a049;
	}

	/* Table pour afficher les fichiers et actions */
	.image-uploader table {
		width: 100%;
		border-collapse: collapse;
		margin-top: 20px;
	}

	/* Styling général de la table */
	.image-uploader table, .image-uploader th, .image-uploader td {
		border: 1px solid #ddd;
		padding: 12px;
		text-align: center;
		background-color: #fff;
		font-size: 14px;
	}

	/* En-têtes de la table */
	.image-uploader th {
		background-color: #f4f4f4;
		font-weight: bold;
		text-transform: uppercase;
	}

	/* Contenu des cellules */
	.image-uploader td {
		background-color: #fff;
		height: 130px; /* Hauteur maximale pour chaque cellule */
		overflow: hidden; /* Cache le contenu qui dépasse */
		text-overflow: ellipsis; /* Affiche des points de suspension (...) pour le texte qui dépasse */
		padding: 12px;
		font-size: 14px;
		vertical-align: middle; /* Aligne verticalement le contenu au centre */
	}

	/* Hauteur maximale des lignes */
	.image-uploader tbody tr {
		max-height: 120px; /* Hauteur maximale des lignes */
		overflow: hidden; /* Cache le contenu excédentaire */
		text-overflow: ellipsis;
	}

	/* Survol des lignes */
	.image-uploader tbody tr:hover {
		background-color: #f7f7f7;
	}

	/* Boutons d'upload, suppression et mise à jour */
	.btn-upload, .btn-delete, .btn-update, .btn-display {
		border: none;
		border-radius: 8px;
		padding: 10px 20px;
		font-size: 16px;
		cursor: pointer;
		transition: background-color 0.3s ease, transform 0.3s ease;
	}

	/* Bouton d'upload vert */
	.btn-upload {
		background-color: #45a049; /* Vert avant */
		color: white;
	}

	/* Effet de survol du bouton d'upload */
	.btn-upload:hover {
		background-color: #4CAF50; /* Vert plus clair au survol */
		transform: translateY(-2px);
	}

	/* Bouton de suppression rouge */
	.btn-delete {
		background-color: #f44336;
		color: white;
	}

	/* Effet de survol du bouton de suppression */
	.btn-delete:hover {
		background-color: #e53935;
		transform: translateY(-2px);
	}

	/* Bouton de mise à jour bleu */
	.btn-update {
		margin-top: 30px;
		background-color: #2196F3;
		color: white;
	}

	/* Effet de survol du bouton de mise à jour */
	.btn-update:hover {
		background-color: #1e88e5;
		transform: translateY(-2px);
	}

	/* Inputs (numérique, date, checkbox) */
	.image-uploader input[type="number"],
	.image-uploader input[type="date"],
	.image-uploader input[type="checkbox"] {
		width: 100%;
		border-radius: 8px;
		padding: 10px;
		border: 1px solid #ddd;
		font-size: 14px;
		background-color: #f9f9f9;
	}

	/* Titre de la section */
	.image-uploader h2 {
		text-align: center;
		color: #333;
		margin-bottom: 20px;
		font-size: 20px;
		font-weight: bold;
	}

	/* Images dans la table */
	.image-uploader img {
		max-width: 120px;
		max-height: 120px;
		border-radius: 8px;
	}

	/* Table sortable */
	#sortableTable th[data-sort] {
		cursor: pointer;
		position: relative;
		background-color: #f9f9f9;
		user-select: none;
		padding-right: 25px;
		transition: background-color 0.3s ease;
	}

	/* Survol des en-têtes pour le tri */
	#sortableTable th[data-sort]:hover {
		background-color: #d1cfcf;
	}

	/* Icônes de tri dans les en-têtes */
	#sortableTable th[data-sort]::after {
		content: '\25B2';
		position: absolute;
		right: 20px;
		font-size: 0.9em;
		opacity: 0.5;
		transition: transform 0.3s ease, opacity 0.3s ease;
	}

	#sortableTable th[data-sort].desc::after {
		content: '\25BC';
	}

	#sortableTable th[data-sort].asc::after {
		opacity: 1;
	}
</style>