<?php

/*
===============================================================================
Contrôleur : SecurityController (Admin)
===============================================================================
Objectif :
    Fournir l'ecran de connexion du back-office, distinct de l'authentification
    JWT utilisee par le site client.

Responsabilites :
    - Afficher le formulaire de connexion et le dernier email saisi en cas
      d'erreur.
    - Afficher le message d'erreur si les identifiants sont invalides.
    - Exposer la route de deconnexion (logout() ne contient aucune logique :
      Symfony intercepte cette route via la cle 'logout' du firewall 'admin'
      dans security.yaml, avant meme que le corps de la methode ne s'execute).

Routes disponibles :
    - GET/POST /admin/login  (Public — c'est la porte d'entree)
    - GET      /admin/logout (Intercepte par le firewall, jamais execute)

Dependances :
    - AuthenticationUtils : Fourni par Symfony pour recuperer la derniere
      erreur d'authentification et le dernier identifiant saisi.
===============================================================================
*/

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * Affiche le formulaire de connexion du back-office.
     *
     * @param AuthenticationUtils $authenticationUtils Fourni par Symfony.
     * @return Response
     */
    #[Route(path: '/admin/login', name: 'admin_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('admin/security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Route de deconnexion. Le corps de cette methode n'est jamais execute :
     * Symfony intercepte cette route grace a la cle 'logout' du firewall
     * 'admin' dans security.yaml.
     *
     * @return void
     */
    #[Route(path: '/admin/logout', name: 'admin_logout')]
    public function logout(): void
    {
        throw new \LogicException('Cette methode ne doit jamais etre atteinte : interceptee par le firewall.');
    }
}
