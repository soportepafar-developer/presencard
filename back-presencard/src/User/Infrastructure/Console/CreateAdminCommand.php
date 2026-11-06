<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Console;

use App\User\Application\Port\PasswordHasher;
use App\User\Domain\Exception\InvalidUser;
use App\User\Domain\PasswordPolicy;
use App\User\Domain\User;
use App\User\Domain\UserRepository;
use App\User\Domain\UserRole;
use DateTimeImmutable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Alta del primer administrador y herramienta de recuperación si se pierde el acceso. */
final class CreateAdminCommand extends Command
{
    protected static $defaultName = 'app:create-admin';

    private UserRepository $users;
    private PasswordHasher $hasher;

    public function __construct(UserRepository $users, PasswordHasher $hasher)
    {
        parent::__construct();
        $this->users = $users;
        $this->hasher = $hasher;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Crea un administrador. Si el correo ya existe, restablece su contraseña y lo deja como administrador activo.')
            ->addArgument('email', InputArgument::REQUIRED, 'Correo del administrador')
            ->addArgument('password', InputArgument::OPTIONAL, 'Contraseña (si se omite se solicita sin mostrarla)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nombre completo (por defecto, la parte local del correo)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = trim((string) $input->getArgument('email'));
        $password = $input->getArgument('password');

        if ($password === null) {
            $question = new Question(sprintf('Contraseña (mínimo %d caracteres)', PasswordPolicy::MIN_LENGTH));
            $question->setHidden(true);
            $password = $io->askQuestion($question);
        }

        try {
            PasswordPolicy::assertValid((string) $password);

            $hash = $this->hasher->hash((string) $password);
            $now = new DateTimeImmutable();
            $user = $this->users->byEmail($email);
            $name = $input->getOption('name');

            if ($user === null) {
                $user = User::register($email, $name ?: ucfirst((string) strstr($email, '@', true)), $hash, UserRole::ADMIN, $now);
                $message = sprintf('Administrador %s creado.', $email);
            } else {
                $user->changePasswordHash($hash, $now);
                $user->changeRole(UserRole::ADMIN, $now);
                $user->activate($now);

                if ($name) {
                    $user->updateProfile($name, $user->email(), $now);
                }

                $message = sprintf('Contraseña de %s restablecida (administrador activo).', $email);
            }

            $this->users->save($user);
            $io->success($message);
        } catch (InvalidUser $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
