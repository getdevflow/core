<?php

declare(strict_types=1);

namespace App\Domain\Product\Query\Trait;

use Qubus\EventDispatcher\ActionFilter\Filter;
use Qubus\Exception\Exception;
use ReflectionException;

use function Qubus\Security\Helpers\esc_html;
use function Qubus\Security\Helpers\purify_html;
use function sprintf;

trait PopulateProductQueryAware
{
    /**
     * Populate an array of values from result query.
     *
     * @param array|null $data
     * @return array|null
     * @throws Exception
     * @throws ReflectionException
     */
    private function populate(?array $data = []): ?array
    {
        $relativeUrl = Filter::getInstance()->applyFilter(
            'product.relative.url',
            sprintf('product/%s/', esc_html($data['product_slug']))
        );

        return [
            'id' => isset($data['product_id']) ? esc_html(string: $data['product_id']) : null,
            'slug' => isset($data['product_slug']) ? esc_html(string: $data['product_slug']) : null,
            'title' => isset($data['product_title']) ? esc_html(string: $data['product_title']) : null,
            'body' => isset($data['product_body']) ? purify_html($data['product_body']) : null,
            'author' => isset($data['product_author']) ? esc_html(string: $data['product_author']) : null,
            'sku' => isset($data['product_sku']) ? esc_html(string: (string) $data['product_sku']) : null,
            'price' => isset($data['product_price']) ? esc_html(string: (string) $data['product_price']) : null,
            'currency' => isset($data['product_currency']) ? esc_html(string: (string) $data['product_currency']) : null,
            
            'purchaseUrl' => isset($data['product_purchase_url']) ?
                    esc_html(string: (string) $data['product_purchase_url']) :
                    null,
            
            'relativeUrl' => $relativeUrl,
            'showInMenu' => isset($data['product_show_in_menu']) ? esc_html(string: (string) $data['product_show_in_menu']) : null,
            'showInSearch' => isset($data['product_show_in_search']) ? esc_html(string: (string) $data['product_show_in_search']) : null,
            
            'featuredImage' => isset($data['product_featured_image']) ?
                    esc_html(string: $data['product_featured_image']) :
                    null,
            
            'status' => isset($data['product_status']) ? esc_html(string: $data['product_status']) : null,
            'created' => isset($data['product_created']) ? esc_html(string: $data['product_created']) : null,
            'createdGmt' => isset($data['product_created_gmt']) ? esc_html(string: $data['product_created_gmt']) : null,
            'published' => isset($data['product_published']) ? esc_html(string: $data['product_published']) : null,
            'publishedGmt' => isset($data['product_published_gmt']) ? esc_html(string: $data['product_published_gmt']) : null,
            'modified' => isset($data['product_modified']) ? esc_html(string: $data['product_modified']) : null,
            
            'modifiedGmt' => isset($data['product_modified_gmt']) ?
                    esc_html(string: $data['product_modified_gmt']) : null,
        ];
    }
}
