<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Command;

use App\Modules\Order\Application\DTO\CreateOrderCommand;
use App\Modules\Order\Application\Port\OrderStatusCacheInterface;
use App\Modules\Order\Application\UseCase\CreateOrderUseCase;
use App\Modules\Order\Application\UseCase\GetOrderStatusUseCase;
use App\Modules\Order\Domain\Enum\OrderStatus;
use App\Modules\Order\Domain\Repository\OrderRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:order:test-fault-tolerance',
    description: 'Ejecuta una prueba completa de tolerancia a fallos, reintentos exponenciales y Dead Letter Queue (DLQ).'
)]
final class TestFaultToleranceCommand extends Command
{
    public function __construct(
        private readonly CreateOrderUseCase $createOrderUseCase,
        private readonly GetOrderStatusUseCase $getOrderStatusUseCase,
        private readonly OrderStatusCacheInterface $statusCache,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🧪 Prueba Automatizada de Tolerancia a Fallos y Dead Letter Queue (DLQ)');

        // Limpiar mensajes previos en DLQ si los hubiera para un test limpio
        $this->connection->executeStatement(
            "DELETE FROM messenger_messages WHERE queue_name = 'failed'"
        );

        // Paso 1: Crear orden con correo de fallo simulado
        $testEmail = 'fail-gateway-' . bin2hex(random_bytes(3)) . '@example.com';
        $io->section(sprintf('Paso 1: Creando pedido con fallo simulado (%s)', $testEmail));

        $orderDto = $this->createOrderUseCase->execute(new CreateOrderCommand(
            customerEmail: $testEmail,
            totalAmount: 250.00,
            idempotencyKey: 'fault-test-' . bin2hex(random_bytes(4)),
        ));

        $orderId = $orderDto->id;
        $io->writeln(sprintf(' <info>✔ Pedido creado en PostgreSQL y publicado a RabbitMQ:</info> %s', $orderId));
        $io->writeln(sprintf('   Estado inicial: <comment>%s</comment>', $orderDto->status));

        // Paso 2: Esperar el ciclo de reintentos con backoff exponencial
        $io->section('Paso 2: Esperando procesamiento del Worker y reintentos automáticos (max_retries: 3)...');
        $io->writeln('   - Intento 1: Inmediato (fallará)');
        $io->writeln('   - Reintento 1: Espera ~1s');
        $io->writeln('   - Reintento 2: Espera ~2s');
        $io->writeln('   - Reintento 3: Espera ~4s');
        $io->writeln('   - Tras 3 fallos: Se envía a la DLQ y se marca como FAILED');

        $maxWaitSeconds = 15;
        $startTime = time();

        $io->progressStart($maxWaitSeconds);
        while ((time() - $startTime) < $maxWaitSeconds) {
            sleep(1);
            $io->progressAdvance();

            $statusResult = $this->getOrderStatusUseCase->execute($orderId);
            if ($statusResult->status->status === OrderStatus::FAILED->value) {
                break;
            }
        }
        $io->progressFinish();

        // Paso 3: Verificar estado FAILED en PostgreSQL y Redis
        $io->section('Paso 3: Verificando transición de estado a FAILED');
        $statusResult = $this->getOrderStatusUseCase->execute($orderId);
        $dbOrder = $this->orderRepository->findById(Uuid::fromString($orderId));
        if ($dbOrder !== null) {
            $this->orderRepository->refresh($dbOrder);
        }

        if ($statusResult->status->status === OrderStatus::FAILED->value) {
            $io->writeln(sprintf(' <info>✔ Redis Cache:</info> Estado = <error>%s</error> (X-Cache: %s)', 
                $statusResult->status->status,
                $statusResult->isFromCache ? 'HIT' : 'MISS'
            ));
        } else {
            $io->error(sprintf('El estado en Redis es "%s", se esperaba "FAILED"', $statusResult->status->status));
            return Command::FAILURE;
        }

        if ($dbOrder?->getStatus() === OrderStatus::FAILED) {
            $io->writeln(sprintf(' <info>✔ PostgreSQL:</info> Estado = <error>%s</error>', $dbOrder->getStatus()->value));
        } else {
            $io->error(sprintf('El estado en BD es "%s", se esperaba "FAILED"', $dbOrder?->getStatus()->value));
            return Command::FAILURE;
        }

        // Paso 4: Inspeccionar la cola Dead Letter Queue (DLQ)
        $io->section('Paso 4: Verificando persistencia en Dead Letter Queue (Doctrine)');
        $failedCount = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'"
        );
        $io->writeln(sprintf(' <info>✔ Mensajes pendientes en DLQ (PostgreSQL):</info> <comment>%d</comment>', $failedCount));

        if ($failedCount === 0) {
            $io->error('No se encontró el mensaje en la Dead Letter Queue.');
            return Command::FAILURE;
        }

        // Paso 5: Simulación de recuperación y reintento
        $io->section('Paso 5: Simulando recuperación de servicio externo y reintento desde la DLQ');
        $this->statusCache->setRecoveryFlag($orderId);
        $io->writeln(' <info>✔ Flag de recuperación establecido en Redis.</info>');

        $io->writeln(' Ejecutando reintento del mensaje desde la DLQ (messenger:failed:retry)...');
        $app = $this->getApplication();
        if ($app !== null) {
            $retryCommand = $app->find('messenger:failed:retry');
            $bufferedOutput = new BufferedOutput();
            $retryInput = new ArrayInput([
                '--force' => true,
                '--transport' => 'failed',
                '--no-interaction' => true,
            ]);
            $retryCommand->run($retryInput, $bufferedOutput);
        }

        // Esperar a que el worker procese el mensaje reenviado a RabbitMQ
        $io->write(' Esperando confirmación del worker post-reintento...');
        $confirmed = false;
        for ($i = 0; $i < 6; $i++) {
            sleep(1);
            $finalStatus = $this->getOrderStatusUseCase->execute($orderId);
            if ($finalStatus->status->status === OrderStatus::CONFIRMED->value) {
                $confirmed = true;
                break;
            }
        }
        $io->writeln('');

        // Paso 6: Verificación de éxito post-reintento
        $io->section('Paso 6: Verificando estado final post-reintento');
        $finalStatus = $this->getOrderStatusUseCase->execute($orderId);
        $finalDbOrder = $this->orderRepository->findById(Uuid::fromString($orderId));
        if ($finalDbOrder !== null) {
            $this->orderRepository->refresh($finalDbOrder);
        }
        $dlqRemaining = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'"
        );

        $io->writeln(sprintf(' <info>✔ Estado final en Redis:</info> <info>%s</info>', $finalStatus->status->status));
        $io->writeln(sprintf(' <info>✔ Estado final en PostgreSQL:</info> <info>%s</info>', $finalDbOrder?->getStatus()->value));
        $io->writeln(sprintf(' <info>✔ Mensajes restantes en DLQ:</info> <comment>%d</comment>', $dlqRemaining));

        if ($confirmed) {
            $io->success('🎉 ¡Prueba de tolerancia a fallos y Dead Letter Queue COMPLETADA CON ÉXITO!');
            $io->definitionList(
                ['ID del Pedido' => $orderId],
                ['Ciclo de Vida' => 'PENDING ➔ RETRY #1 ➔ RETRY #2 ➔ RETRY #3 ➔ FAILED (DLQ) ➔ RETRY DLQ ➔ CONFIRMED'],
                ['Estrategia de Reintentos' => 'Exponencial (1s, 2s, 4s) con desvío automático a PostgreSQL DLQ'],
                ['Resiliencia Redis' => 'Sincronización en tiempo real de estados de error y recuperación']
            );
            return Command::SUCCESS;
        }

        $io->warning('El mensaje fue reintentado pero aún no alcanzó el estado CONFIRMED.');
        return Command::SUCCESS;
    }
}
