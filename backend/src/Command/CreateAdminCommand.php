<?php

/*
===============================================================================
Commande : app:create-admin
===============================================================================
Objectif :
    Creer un nouvel utilisateur avec le role ROLE_ADMIN, ou promouvoir un
    utilisateur existant.

Responsabilites :
    - Si l'email n'existe pas encore : creer le compte avec le mot de passe fourni.
    - Si l'email existe deja : ajouter ROLE_ADMIN a ses roles existants.

Pourquoi une commande console et non un endpoint API :
    Aucune route HTTP ne doit jamais permettre a un utilisateur de s'attribuer
    ROLE_ADMIN lui-meme. Cette action reste volontairement reservee a une
    execution manuelle, en local ou sur le serveur, par une personne ayant
    deja acces au systeme de fichiers/console.

Exemple d'utilisation :
    php bin/console app:create-admin admin@volo.fr "MotDePasse#2024"
    php bin/console app:create-admin sophie@example.com   (promeut un compte existant)
===============================================================================
*/

namespace App\Command;

use App\Entity\User;
use App\Enum\UserRole;
use App\Service\PasswordValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Cree un utilisateur ROLE_ADMIN, ou promeut un utilisateur existant.',
)]
class CreateAdminCommand extends Command
{
    /**
     * @param EntityManagerInterface $entityManager Pour persister/mettre a jour l'utilisateur.
     * @param UserPasswordHasherInterface $passwordHasher Pour hasher le mot de passe si creation.
     * @param PasswordValidator $passwordValidator Pour appliquer la meme politique que l'inscription publique.
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private PasswordValidator $passwordValidator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'administrateur')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe (requis uniquement pour un nouveau compte)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = trim($input->getArgument('email'));
        $password = $input->getArgument('password');

        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $email]);

        if ($user) {
            $roles = $user->getRoles();
            if (in_array(UserRole::ADMIN->value, $roles, true)) {
                $io->warning(sprintf('%s est deja administrateur.', $email));
                return Command::SUCCESS;
            }
            $roles[] = UserRole::ADMIN->value;
            $user->setRoles($roles);
            $this->entityManager->flush();

            $io->success(sprintf('%s a ete promu administrateur.', $email));
            return Command::SUCCESS;
        }

        if (!$password) {
            $io->error('Le mot de passe est requis pour creer un nouvel utilisateur.');
            return Command::FAILURE;
        }

        $passwordErrors = $this->passwordValidator->validate($password);
        if (!empty($passwordErrors)) {
            $io->error($passwordErrors);
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Admin');
        $user->setLastName('VOLO');
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles([UserRole::ADMIN->value]);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Administrateur %s cree.', $email));

        return Command::SUCCESS;
    }
}
