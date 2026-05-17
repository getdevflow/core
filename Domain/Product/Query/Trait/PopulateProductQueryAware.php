<?php

declare(strict_types=1);

namespace App\Domain\Product\Query\Trait;

use App\Infrastructure\Services\Trait\CleanAware;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Qubus\EventDispatcher\ActionFilter\Filter;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\cms_render_content;
use function Qubus\Security\Helpers\esc_html;
use function Qubus\Security\Helpers\purify_html;
use function sprintf;

trait PopulateProductQueryAware
{
    use CleanAware;

    /**
     * Populate an array of values from result query.
     *
     * @param array|null $data
     * @return array|null
     * @throws Exception
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function populate(?array $data = []): ?array
    {
        $relativeUrl = Filter::getInstance()->applyFilter(
            'product.relative.url',
            sprintf('product/%s/', esc_html($data['product_slug']))
        );

        return [
            'id' => $this->clean($data['product_id']),
            'slug' => $this->clean($data['product_slug']),
            'title' => $this->clean($data['product_title']),
            'body' => isset($data['product_body']) ? purify_html(cms_render_content($data['product_body'])) : null,
            'author' => $this->clean($data['product_author']),
            'sku' => $this->clean((string) $data['product_sku']),
            'price' => $this->clean((string) $data['product_price']),
            'currency' => $this->clean((string) $data['product_currency']),

            'purchaseUrl' => $this->clean((string) $data['product_purchase_url']),

            'relativeUrl' => $relativeUrl,
            'showInMenu' => $this->clean((string) $data['product_show_in_menu']),
            'showInSearch' => $this->clean((string) $data['product_show_in_search']),

            'featuredImage' => $this->clean($data['product_featured_image']),

            'status' => $this->clean($data['product_status']),
            'created' => $this->clean($data['product_created']),
            'createdGmt' => $this->clean($data['product_created_gmt']),
            'published' => $this->clean($data['product_published']),
            'publishedGmt' => $this->clean($data['product_published_gmt']),
            'modified' => $this->clean($data['product_modified']),

            'modifiedGmt' => $this->clean($data['product_modified_gmt']),
        ];
    }
}
