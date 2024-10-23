document.getElementById('image-input').addEventListener('change', function() {
    const fileInput = document.getElementById('image-input');
    const file = fileInput.files[0];
    const preview = document.getElementById('image-preview');
    const confirmUploadButton = document.getElementById('confirm-upload');
    const submitButton = document.getElementById('submit-btn');

    // Vérifier si un fichier a été sélectionné
    if (file) {
        const reader = new FileReader();

        // Événement déclenché lorsque le fichier est chargé
        reader.onload = function(event) {
            const img = document.createElement('img');
            img.src = event.target.result;
            preview.innerHTML = ''; // Effacer l'ancien aperçu
            preview.appendChild(img); // Ajouter la nouvelle image

            // Afficher le bouton de confirmation
            confirmUploadButton.style.display = 'inline-block';
            submitButton.style.display = 'inline-block';
        };

        reader.readAsDataURL(file); // Lire le fichier comme une URL de données
    }

    // Gérer le clic sur le bouton de confirmation
    confirmUploadButton.onclick = function() {
        const confirmed = confirm("Êtes-vous sûr de vouloir télécharger cette image ?");
        if (confirmed) {
            // Si l'utilisateur confirme, soumettre le formulaire
            submitButton.click();
        }
    };
});
