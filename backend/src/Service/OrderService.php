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
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Http\JsonBody;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    /**
     * Fenetre pendant laquelle une commande en attente identique est
     * reutilisee plutot que dupliquee.
     *
     * Elle doit rester PLUS COURTE que le delai du balayage des paniers
     * abandonnes (app:release-stale-orders, 60 minutes) : sinon on renverrait
     * au client une commande sur le point d'etre annulee sous ses yeux.
     */
    private const FENETRE_REUTILISATION_MINUTES = 30;

    private EntityManagerInterface $entityManager;
    private ProductRepository $productRepository;
    private OrderRepository $orderRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
    ) {
        $this->entityManager = $entityManager;
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Crée une commande et ses items — ou renvoie la commande en attente identique.
     *
     * Recharger la page de commande, cliquer deux fois ou ouvrir deux onglets
     * creait auparavant une nouvelle commande a chaque fois, chacune reservant
     * du stock jusqu'au balayage. Si le client a deja, depuis moins de
     * FENETRE_REUTILISATION_MINUTES, une commande en attente contenant
     * exactement les memes produits et quantites, elle est renvoyee (adresse
     * mise a jour) et le stock n'est PAS reserve une seconde fois.
     *
     * D'ou l'ordre des etapes : valider le format, chercher la commande
     * identique, et seulement ensuite verifier et reserver le stock — sinon
     * une commande deja reservee paraitrait manquer de stock pour elle-meme.
     *
     * @param array<mixed> $orderData Données JSON reçues (items, shippingAddress)
     * @param object $user L'utilisateur connecté (User)
     * @return Order La commande créée, ou la commande en attente identique
     * @throws \InvalidArgumentException Si les données sont invalides ou le stock insuffisant
     */
    public function createOrder(array $orderData, object $user): Order
    {
        // 1. Traitement de l'adresse de livraison
        //
        // L'adresse est OBLIGATOIRE : les colonnes street/city/postalCode de
        // shop_order sont NOT NULL. Quand ce bloc etait conditionne par un
        // `if (isset(...))`, une requete sans 'shippingAddress' traversait la
        // validation sans rien declencher, puis echouait au flush sur la
        // contrainte SQL : le client recevait un 500 la ou son erreur meritait
        // un 400. On refuse donc explicitement, au bon niveau.
        if (!isset($orderData['shippingAddress']) || !is_array($orderData['shippingAddress'])) {
            throw new \InvalidArgumentException('L\'adresse de livraison est requise.');
        }

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

        // 2. Lignes demandees : format valide AVANT tout acces au stock.
        if (!isset($orderData['items']) || !is_array($orderData['items']) || $orderData['items'] === []) {
            throw new \InvalidArgumentException("La liste des items est manquante ou invalide.");
        }

        /** @var list<array{0: int, 1: int}> $lignes */
        $lignes = [];
        foreach ($orderData['items'] as $itemData) {
            if (!is_array($itemData)) {
                throw new \InvalidArgumentException('Format d\'item invalide.');
            }
            // Entiers STRICTS : `(int)` convertissait silencieusement "12,50" en 12
            // et `true` en 1 — une commande de 12 unites acceptee pour une saisie
            // invalide. Seuls un entier JSON ou une chaine de chiffres passent.
            $productId = JsonBody::int($itemData['productId'] ?? null) ?? 0;
            $quantity = JsonBody::int($itemData['quantity'] ?? null) ?? 0;

            if ($productId <= 0) {
                throw new \InvalidArgumentException('Identifiant produit invalide.');
            }
            if ($quantity <= 0 || $quantity > 1000) {
                throw new \InvalidArgumentException('La quantite doit etre comprise entre 1 et 1000.');
            }

            $lignes[] = [$productId, $quantity];
        }

        // 3. Commande en attente identique : on la renvoie, sans reserver le
        // stock une seconde fois. Seule l'adresse, peut-etre corrigee par le
        // client entre-temps, est mise a jour.
        if ($user instanceof User) {
            $existante = $this->trouverCommandeIdentique($user, $lignes);
            if ($existante !== null) {
                $existante->setStreet($street);
                $existante->setCity($city);
                $existante->setPostalCode($postalCode);
                $existante->setCountry($country);
                $this->entityManager->flush();

                return $existante;
            }
        }

        // 4. Nouvelle commande : disponibilite, stock, reservation.
        $order = new Order();
        $order->setUser($user);
        $order->setStatus(OrderStatus::PENDING);
        $order->setStreet($street);
        $order->setCity($city);
        $order->setPostalCode($postalCode);
        $order->setCountry($country);

        $totalAmount = 0;

        foreach ($lignes as [$productId, $quantity]) {
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
            $totalAmount += $unitPrice * $quantity;

            // Création de l'OrderItem
            $orderItem = new OrderItem();
            $orderItem->setOrderEntity($order);
            $orderItem->setProduct($product);
            $orderItem->setQuantity($quantity);
            $orderItem->setUnitPrice(number_format($unitPrice, 2, '.', ''));
            $orderItem->setProductName($product->getName());

            $order->addItem($orderItem);
        }

        // 5. Définir le total de la commande
        if ($totalAmount == 0) {
            throw new \InvalidArgumentException("Le montant total de la commande ne peut pas être de zéro.");
        }

        $order->setTotal(number_format($totalAmount, 2, '.', ''));

        // 6. Persistance
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    /**
     * Commande en attente recente du client, contenant exactement les memes
     * produits et quantites que la demande.
     *
     * La comparaison porte sur les quantites AGREGEES par produit : deux lignes
     * « produit 1 x1 » valent une ligne « produit 1 x2 ».
     *
     * @param list<array{0: int, 1: int}> $lignes Couples [identifiant produit, quantite]
     */
    private function trouverCommandeIdentique(User $user, array $lignes): ?Order
    {
        $demande = self::quantitesParProduit($lignes);
        // Heure calculee cote PHP, comme createdAt : comparer a NOW() de MySQL
        // introduirait le decalage de fuseau deja rencontre (PHP en UTC).
        $depuis = new \DateTimeImmutable(sprintf('-%d minutes', self::FENETRE_REUTILISATION_MINUTES));

        foreach ($this->orderRepository->findRecentPendingForUser($user, $depuis) as $candidate) {
            $existant = [];
            foreach ($candidate->getItems() as $item) {
                $pid = $item->getProduct()?->getId();
                $qty = $item->getQuantity();
                if ($pid === null || $qty === null) {
                    continue 2;
                }
                $existant[] = [$pid, $qty];
            }

            if (self::quantitesParProduit($existant) === $demande) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param list<array{0: int, 1: int}> $lignes
     * @return array<int, int> Quantite totale par identifiant produit, triee par identifiant
     */
    private static function quantitesParProduit(array $lignes): array
    {
        $parProduit = [];
        foreach ($lignes as [$productId, $quantity]) {
            $parProduit[$productId] = ($parProduit[$productId] ?? 0) + $quantity;
        }
        ksort($parProduit);

        return $parProduit;
    }

    /**
     * Restitue au stock les unites retenues par une commande.
     *
     * Le stock est retire des la creation de la commande (statut pending),
     * avant tout paiement : c'est une reservation. Cette methode en est la
     * contrepartie, a appeler des que la reservation tombe — paiement
     * echoue, annulation, ou commande abandonnee.
     *
     * NE FLUSHE PAS : l'appelant maitrise sa transaction, et c'est ce qui
     * permet de restituer plusieurs commandes en un seul flush.
     *
     * L'IDEMPOTENCE EST A LA CHARGE DE L'APPELANT. Rien ici n'empeche une
     * double restitution ; les appelants s'appuient sur la machine a etats,
     * dont la garde ne laisse passer une transition qu'une seule fois.
     *
     * @return int Nombre d'unites effectivement restituees.
     */
    public function releaseStock(Order $order): int
    {
        $restituees = 0;

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            $quantity = $item->getQuantity();

            // Un produit supprime depuis la commande n'a plus de stock a
            // recevoir : la ligne garde son snapshot (nom, prix), pas la
            // relation. On ignore sans echouer, le reste doit passer.
            if ($product === null || $quantity === null || $quantity <= 0) {
                continue;
            }

            $product->incrementStock($quantity);
            $restituees += $quantity;
        }

        return $restituees;
    }
}