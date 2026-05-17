<?php

declare(strict_types=1);

namespace App\Domain\Content\Query\Trait;

use App\Infrastructure\Services\Trait\CleanAware;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\cms_render_content;
use function Codefy\Framework\Helpers\config;
use function Qubus\Security\Helpers\purify_html;

trait PopulateContentQueryAware
{
    use CleanAware;

    /**
     * Populate an array of values from result query.
     *
     * @param array|null $data
     * @return array|null
     * @throws Exception
     * @throws TypeException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    private function populate(?array $data = []): ?array
    {
        if (config()->string(key: 'cms.relative_url') === 'contenttype') {
            $relativeUrl = $data['content_type'] . '/' . $data['content_slug'] . '/';
        } else {
            $relativeUrl = $data['content_slug'] . '/';
        }

        return [
            'id' => $this->clean($data['content_id']),
            'slug' => $this->clean($data['content_slug']),
            'title' => $this->clean($data['content_title']),
            'body' => isset($data['content_body']) ? purify_html(cms_render_content($data['content_body'])) : null,
            'author' => $this->clean($data['content_author']),
            'type' => $this->clean($data['content_type']),
            'parent' => $this->clean($data['content_parent']),
            'sidebar' => $this->clean((string) $data['content_sidebar']),
            'showInMenu' => $this->clean((string) $data['content_show_in_menu']),
            'showInSearch' => $this->clean((string) $data['content_show_in_search']),
            'relativeUrl' => $this->clean($relativeUrl),
            'featuredImage' => $this->clean($data['content_featured_image']),
            'status' => $this->clean($data['content_status']),
            'created' => $this->clean($data['content_created']),
            'createdGmt' => $this->clean($data['content_created_gmt']),
            'published' => $this->clean($data['content_published']),
            'publishedGmt' => $this->clean($data['content_published_gmt']),
            'modified' => $this->clean($data['content_modified']),
            'modifiedGmt' => $this->clean($data['content_modified_gmt']),
        ];
    }
}
