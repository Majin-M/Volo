<?php

namespace App\Controller\Admin;

use App\Entity\Brand;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\SkinConcern;
use App\Entity\User;
use App\Controller\Admin\ProductCrudController; 
use App\Controller\Admin\BrandCrudController;
use App\Controller\Admin\SkinConcernCrudController;
use App\Controller\Admin\OrderCrudController;
use App\Controller\Admin\UserCrudController;


use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        // Redirection vers la liste des produits par défaut
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        
        return $this->redirect(
            $adminUrlGenerator
                ->setController(ProductCrudController::class)
                ->generateUrl()
        );
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<img src="/volo-logo.svg" style="height: 30px"> VOLO Admin')
            ->setFaviconPath('favicon.svg');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::linkToRoute('Retour au site', 'fa fa-external-link-alt', 'app_home');

        yield MenuItem::section('Catalogue');
        
       
        yield MenuItem::linkTo(ProductCrudController::class,'Produits', 'fa fa-tags' );
        yield MenuItem::linkTo(BrandCrudController::class,'Marques', 'fa fa-building');
        yield MenuItem::linkTo(SkinConcernCrudController::class,'Problématiques', 'fa fa-stethoscope');

        yield MenuItem::section('Ventes');
        yield MenuItem::linkTo(OrderCrudController::class, 'Commandes', 'fa fa-shopping-cart');
        yield MenuItem::linkTo(UserCrudController::class,'Clients', 'fa fa-users');
    }
}
