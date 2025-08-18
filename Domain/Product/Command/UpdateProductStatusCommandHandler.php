<?php

declare(strict_types=1);

namespace App\Domain\Product\Command;

use App\Domain\Product\Product;
use App\Domain\Product\Repository\ProductAggregateRepository;
use Codefy\CommandBus\Command;
use Codefy\CommandBus\CommandHandler;
use Codefy\Domain\Aggregate\AggregateNotFoundException;
use Exception;

class UpdateProductStatusCommandHandler implements CommandHandler
{
    public function __construct(public ProductAggregateRepository $aggregateRepository)
    {
    }

    /**
     * @inheritDoc
     * @throws AggregateNotFoundException
     * @throws Exception
     */
    public function handle(UpdateProductStatusCommand|Command $command): void
    {
        /** @var Product $product */
        $product = $this->aggregateRepository->loadAggregateRoot($command->productId);

        $product->changeProductStatus($command->productStatus);
        if ($product->hasRecordedEvents()) {
            $product->changeProductModified($command->productModified);
            $product->changeProductModifiedGmt($command->productModifiedGmt);
        }

        $this->aggregateRepository->saveAggregateRoot($product);
    }
}
