<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Create a dashboard user for ClawHQ.')]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addArgument('password', InputArgument::REQUIRED, 'Plain password')
            ->addOption('role', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Additional roles (repeatable), e.g. ROLE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = strtolower(trim((string) $input->getArgument('email')));
        $password = (string) $input->getArgument('password');
        $roles = array_values(array_filter(array_map('trim', (array) $input->getOption('role'))));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Invalid email address.');

            return Command::INVALID;
        }

        if (strlen($password) < 8) {
            $io->error('Password must be at least 8 characters.');

            return Command::INVALID;
        }

        if ($this->userRepository->findOneBy(['email' => $email]) !== null) {
            $io->error(sprintf('User "%s" already exists.', $email));

            return Command::FAILURE;
        }

        $user = (new User())
            ->setEmail($email)
            ->setRoles($roles);

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Created user %s', $email));

        return Command::SUCCESS;
    }
}
