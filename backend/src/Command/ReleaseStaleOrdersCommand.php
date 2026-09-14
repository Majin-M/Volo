<?php

/*
===============================================================================
Commande : app:release-stale-orders
===============================================================================
Objectif :
    Annuler les commandes restees impayees au-dela d'un delai, et restituer
    le stock qu'elles immobilisaient.

Le probleme qu'elle resout :
    Le stock est retire des la CREATION de la commande, donc au statut
    'pending', avant tout paiement — c'est une reservation. Quand le client
    abandonne son panier, cette reservation n'est jamais levee : les unites
    restent immobilisees indefiniment. C'est le cas le plus frequent en
    pratique, bien avant l'echec de paiement (traite, lui, par le webhook).

Pourquoi une commande planifiee et non un declenchement a chaud :
    L'abandon n'est pas un evenement — personne ne previent qu'un panier ne
    sera pas paye. Seul l'ecoulement du temps le revele, ce qui suppose un
    balayage periodique.

Exemple d'utilisation :
    php bin/console app:release-stale-orders --dry-run
    php bin/console app:release-stale-orders --minutes=30

Cron conseille (toutes les 15 minutes) :
    *\/15 * * * * php /chemin/bin/console app:release-stale-orders
===============================================================================
*/

namespace App\Command;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsCommand(
    name: 'app:release-stale-orders',
    description: 'Annule les commandes impayees trop anciennes et restitue leur stock.',
)]
class ReleaseStaleOrdersCommand extends Command
{
    private const DELAI_DEFAUT_MINUTES = 60;

    public function __construct(
        private OrderRepository $orderRepository,
        private OrderService $orderService,
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'state_machine.order')] private WorkflowInterface $orderStateMachine,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'minutes',
                null,
                InputOption::VALUE_REQUIRED,
                'Age minimal, en minutes, d\'une commande impayee pour etre annulee.',
                (string) self::DELAI_DEFAUT_MINUTES,
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Affiche ce qui serait fait, sans rien modifier.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $optionMinutes = $input->getOption('minutes');
        $minutes = is_numeric($optionMinutes) ? (int) $optionMinutes : 0;
        if ($minutes < 1) {
            $io->error('L\'option --minutes doit valoir au moins 1.');

            return Command::INVALID;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $limite = new \DateTimeImmutable(sprintf('-%d minutes', $minutes));

        $commandes = $this->orderRepository->findStalePending($limite);

        if ($commandes === []) {
            $io->success(sprintf(
                'Aucune commande impayee de plus de %d minutes. Rien a faire.',
                $minutes,
            ));

            return Command::SUCCESS;
        }

        $io->title(sprintf(
            '%d commande(s) impayee(s) depuis plus de %d minutes',
            \count($commandes),
            $minutes,
        ));

        $lignes = [];
        $totalRestitue = 0;
        $ignorees = 0;

        foreach ($commandes as $order) {
            // La machine a etats reste l'autorite : si la transition n'est
            // pas permise, on n'y touche pas plutot que de forcer le statut.
            if (!$this->orderStateMachine->can($order, 'cancel_pending')) {
                ++$ignorees;
                continue;
            }

            $unites = $dryRun
                ? $this->compterUnites($order)
                : $this->orderService->releaseStock($order);

            if (!$dryRun) {
                $this->orderStateMachine->apply($order, 'cancel_pending');
            }

            $totalRestitue += $unites;
            $lignes[] = [
                $order->getId(),
                $order->getCreatedAt()->format('d/m/Y H:i'),
                $order->getTotal() . ' €',
                $unites,
            ];
        }

        $io->table(['Commande', 'Creee le', 'Montant', 'Unites restituees'], $lignes);

        if ($dryRun) {
            $io->note(sprintf(
                'Simulation : %d unite(s) seraient restituees. Relancez sans --dry-run pour appliquer.',
                $totalRestitue,
            ));

            return Command::SUCCESS;
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d commande(s) annulee(s), %d unite(s) restituees au stock.',
            \count($lignes),
            $totalRestitue,
        ));

        if ($ignorees > 0) {
            $io->warning(sprintf(
                '%d commande(s) ignoree(s) : transition "cancel_pending" refusee par la machine a etats.',
                $ignorees,
            ));
        }

        return Command::SUCCESS;
    }

    private function compterUnites(Order $order): int
    {
        $total = 0;
        foreach ($order->getItems() as $item) {
            $total += $item->getQuantity() ?? 0;
        }

        return $total;
    }
}
