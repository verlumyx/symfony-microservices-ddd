<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Command;

use App\Modules\Order\Application\Port\OrderStatusCacheInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:order:recover',
    description: 'Marca un pedido para simular recuperación exitosa tras un fallo en la pasarela externa.'
)]
final class RecoverOrderCommand extends Command
{
    public function __construct(
        private readonly OrderStatusCacheInterface $statusCache,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('orderId', InputArgument::REQUIRED, 'UUID del pedido a recuperar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $orderId = (string) $input->getArgument('orderId');

        $this->statusCache->setRecoveryFlag($orderId);

        $io->success(sprintf('Flag de recuperación establecido en Redis para el pedido: %s', $orderId));
        $io->info('Ahora puedes ejecutar "make messenger-retry" para reintentar el procesamiento exitosamente.');

        return Command::SUCCESS;
    }
}
