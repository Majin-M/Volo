/*
===============================================================================
Script : ecran de connexion du back-office VOLO
===============================================================================
Objectif :
    Empecher le double envoi du formulaire de connexion.

Pourquoi :
    La connexion regenere la session et invalide le jeton CSRF du formulaire.
    Un second envoi (double-clic, Entree puis clic) arrivait donc juste apres
    une connexion reussie et echouait sur "Invalid CSRF token".

Fichier externe et non <script> inline : la Content-Security-Policy du site
(`script-src 'self'`, voir SecurityHeadersSubscriber) bloque le JS inline.
===============================================================================
*/

document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('.login-card form');
    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {
        if (form.dataset.submitted === '1') {
            event.preventDefault();
            return;
        }
        form.dataset.submitted = '1';

        var button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.textContent = 'Connexion…';
        }
    });
});
