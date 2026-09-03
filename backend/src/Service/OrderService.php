<?php

/*
===============================================================================
Service : OrderService
===============================================================================
Objectif :
    Gérer la logique de création et de gestion des commandes.

Responsabilités :
    - Créer une commande à partir d'une liste de produits.
    - Enregistrer l'adresse de livraison fournie.
    - Calculer le montant total de la commande.
    - Créer les lignes de commande (OrderItem).
    - Associer la commande à l'utilisateur connecté.

Dépendances :
    - EntityManagerInterface : Pour persister les données.
    - ProductRepository : Pour vérifier l'existence et le prix des produits.
===============================================================================
*/

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    private EntityManagerInterface $entityManager;
    private ProductRepository $productRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProductRepository $productRepository
    ) {
        $this->entityManager = $entityManager;
        $this->productRepository = $productRepository;
    }

    /**
     * Crée une commande et ses items.
     *
     * @param array $orderData Données JSON reçues (items, shippingAddress)
     * @param object $user L'utilisateur connecté (User)
     * @return Order L'entité Order créée
     * @throws \InvalidArgumentException Si un produit n'existe pas
     */
    public function createOrder(array $orderData, object $user): Order
    {
        $order = new Order();
        $order->setUser($user);
        $order->setStatus(\App\Enum\OrderStatus::PENDING);

        // 1. Traitement de l'adresse de livraison
        if (isset($orderData['shippingAddress']) && is_array($orderData['shippingAddress'])) {
            /** @var array<string, mixed> $addr */
            $addr = $orderData['shippingAddress'];
            $street = strip_tags(trim(is_string($addr['street'] ?? null) ? $addr['street'] : ''));
            $city = strip_tags(trim(is_string($addr['city'] ?? null) ? $addr['city'] : ''));
            $postalCode = strip_tags(trim(is_string($addr['postalCode'] ?? null) ? $addr['postalCode'] : ''));
            $country = strip_tags(trim(is_string($addr['country'] ?? null) ? $addr['country'] : 'France'));

            if ($street === '' || $city === '' || $postalCode === '') {
                throw new \InvalidArgumentException('L\'adresse de livraison est incomplete (rue, ville et code postal requis).');
            }
            if (mb_strlen($street) > 255 || mb_strlen($city) > 100 || mb_strlen($postalCode) > 20 || mb_strlen($country) > 100) {
                throw new \InvalidArgumentException('Un champ de l\'adresse depasse la longueur maximale autorisee.');
            }

            $order->setStreet($street);
            $order->setCity($city);
            $order->setPostalCode($postalCode);
            $order->setCountry($country);
        }

        $totalAmount = 0;

        // 2. Traitement des items (produits)
        if (isset($orderData['items']) && is_array($orderData['items'])) {
            foreach ($orderData['items'] as $itemData) {
                if (!is_array($itemData)) {
                    throw new \InvalidArgumentException('Format d\'item invalide.');
                }
                $productId = isset($itemData['productId']) ? (int) $itemData['productId'] : 0;
                $quantity = isset($itemData['quantity']) ? (int) $itemData['quantity'] : 0;

                if ($productId <= 0) {
                    throw new \InvalidArgumentException('Identifiant produit invalide.');
                }
                if ($quantity <= 0 || $quantity > 1000) {
                    throw new \InvalidArgumentException('La quantite doit etre comprise entre 1 et 1000.');
                }

                // Vérifier si le produit existe
                $product = $this->productRepository->find($productId);

                if (!$product) {
                    throw new \InvalidArgumentException("Le produit avec l'ID $productId n'existe pas.");
                }

                if (!$product->isAvailable()) {
                    throw new \InvalidArgumentException("Le produit {$product->getName()} n'est pas disponible.");
                }

                if ($product->getStock() < $quantity) {
                    throw new \InvalidArgumentException(sprintf(
                        'Stock insuffisant pour "%s" : %d demande(s), %d disponible(s).',
                        $product->getName(),
                        $quantity,
                        $product->getStock(),
                    ));
                }

                $product->decrementStock($quantity);

                // Calcul du prix de la ligne
                $unitPrice = (float) $product->getPrice();
                $lineTotal = $unitPrice * $quantity;
                $totalAmount += $lineTotal;

                // Création de l'OrderItem
                $orderItem = new OrderItem();
                $orderItem->setOrderEntity($order);
                $orderItem->setProduct($product);
                $orderItem->setQuantity($quantity);
                $orderItem->setUnitPrice(number_format($unitPrice, 2, '.', ''));
                $orderItem->setProductName($product->getName());

                $order->addItem($orderItem);
            }
        } else {
            throw new \InvalidArgumentException("La liste des items est manquante ou invalide.");
        }

        // 3. Définir le total de la commande
        if ($totalAmount == 0) {
            throw new \InvalidArgumentException("Le montant total de la commande ne peut pas être de zéro.");
        }
        
        $order->setTotal(number_format($totalAmount, 2, '.', ''));

        // 4. Persistance
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }
}