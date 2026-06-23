<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Site;

use Qubus\Exception\Exception;
use Qubus\Expressive\Database;
use Qubus\Expressive\Schema\CreateTable;

final class SiteSchema
{
    public function __construct(protected Database $dfdb, protected string $prefix)
    {
    }

    /**
     * @return void
     * @throws Exception
     */
    public function migrateUp(): void
    {
        $this->eventStore();
        $this->content();
        $this->option();
        $this->plugin();
        $this->product();
        $this->elfinderFile();
        $this->elfinderTrash();
        $this->pages();
        $this->uploads();
        $this->pageTranslations();
        $this->settings();
        $this->contentComment();
        $this->contentActivity();
        $this->contentNotification();
    }

    /**
     * @throws Exception
     */
    private function eventStore(): void
    {
        $this->dfdb->schema()->create(
            table: $this->prefix . 'event_store',
            callback: function (CreateTable $table) {
                $table->string(
                    name: 'event_id',
                    length: 36
                )->primary(name: 'eventId')->unique(name: $this->prefix . 'eventId');
                $table->string(name: 'transaction_id', length: 36);
                $table->string(name: 'event_type', length: 191)->notNull();
                $table->string(name: 'event_classname', length: 191)->notNull();
                $table->text(name: 'payload')->size(value: 'big')->notNull();
                $table->text(name: 'metadata')->size(value: 'big')->notNull();
                $table->string(name: 'aggregate_id', length: 36)->notNull();
                $table->string(name: 'aggregate_type', length: 191)->notNull();
                $table->integer(name: 'aggregate_playhead')->size(value: 'large')->notNull();
                $table->dateTime(name: 'recorded_at')->notNull();
                $table->unique(
                    columns: ['aggregate_id','aggregate_type','aggregate_playhead'],
                    name: $this->prefix . 'domainEvent'
                );
            }
        );
    }

    /**
     * @throws Exception
     */
    private function content(): void
    {
        $this->dfdb->schema()
            ->create(
                table: $this->prefix . 'content_type',
                callback: function (CreateTable $table) {
                    $table->string(name: 'content_type_id', length: 36)
                        ->primary(name: 'contentTypeId')
                        ->unique(name: $this->prefix . 'contentTypeId');
                    $table->string(name: 'content_type_title', length: 191);
                    $table->string(name: 'content_type_slug', length: 191)->unique($this->prefix . 'content_type_slug');
                    $table->text(name: 'content_type_description')->size(value: 'big');
                    $table->index('content_type_slug', $this->prefix . 'contentTypeIndex');
                }
            );

        $this->dfdb->schema()
            ->create(table: $this->prefix . 'content', callback: function (CreateTable $table) {
                $table->string(name: 'content_id', length: 36)
                    ->primary('contentId')
                    ->unique(name: $this->prefix . 'contentId');
                $table->string(name: 'content_title', length: 191)->notNull();
                $table->string(name: 'content_slug', length: 191)->notNull();
                $table->text(name: 'content_body')->size(value: 'big');
                $table->text(name: 'content_attribute')->size(value: 'big');
                $table->string(name: 'content_author', length: 36);
                $table->string(name: 'content_type', length: 191)->notNull();
                $table->string(name: 'content_parent', length: 36);
                $table->integer(name: 'content_sidebar')->size(value: 'large')->defaultValue(0);
                $table->integer(name: 'content_show_in_menu')->size(value: 'large')->defaultValue(0);
                $table->integer(name: 'content_show_in_search')->size(value: 'large')->defaultValue(0);
                $table->string(name: 'content_featured_image', length: 191);
                $table->string(name: 'content_status', length: 36)->notNull()->defaultValue('draft');
                $table->string(name: 'content_created', length: 191);
                $table->dateTime(name: 'content_created_gmt');
                $table->string(name: 'content_published', length: 191);
                $table->dateTime(name: 'content_published_gmt');
                $table->string(name: 'content_modified', length: 191);
                $table->dateTime(name: 'content_modified_gmt');
                $table->index(['content_slug','content_type','content_parent'], $this->prefix . 'contentIndex');

                $table->foreign('content_author', $this->prefix . 'contentAuthor')
                    ->references($this->dfdb->basePrefix . 'user', 'user_id')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
                $table->foreign('content_type', $this->prefix . 'contentTypeSlug')
                    ->references($this->prefix . 'content_type', 'content_type_slug')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
                $table->foreign('content_parent', $this->prefix . 'contentParent')
                    ->references($this->prefix . 'content', 'content_id')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            });
    }

    /**
     * @throws Exception
     */
    private function option(): void
    {
        $this->dfdb->schema()
            ->create(table: $this->prefix . 'option', callback: function (CreateTable $table) {
                $table->string(name: 'option_id', length: 36)
                    ->primary(name: 'optionId')
                    ->unique(name: $this->prefix . 'optionId');
                $table->string(name: 'option_key', length: 191)->unique(name: $this->prefix . 'option_key');
                $table->text(name: 'option_value')->size(value: 'big');
                $table->unique('option_key', $this->prefix . 'optionIndex');
            });
    }

    /**
     * @throws Exception
     */
    private function plugin(): void
    {
        $this->dfdb->schema()
            ->create(
                table: $this->prefix . 'plugin',
                callback: function (CreateTable $table) {
                    $table->string(name: 'plugin_id', length: 36)
                        ->primary(name: 'pluginId')
                        ->unique(name: $this->prefix . 'pluginId');
                    $table->string(name: 'plugin_classname', length: 191);
                    $table->unique('plugin_classname', $this->prefix . 'pluginIndex');
                }
            );
    }

    /**
     * @throws Exception
     */
    private function product(): void
    {
        $this->dfdb->schema()
            ->create(table: $this->prefix . 'product', callback: function (CreateTable $table) {
                $table->string(name: 'product_id', length: 36)
                    ->primary('productId')
                    ->unique(name: $this->prefix . 'productId');
                $table->string(name: 'product_title', length: 191)->notNull();
                $table->string(name: 'product_slug', length: 191)->notNull();
                $table->text(name: 'product_body')->size(value: 'big');
                $table->text(name: 'product_attribute')->size(value: 'big');
                $table->string(name: 'product_author', length: 36);
                $table->string(name: 'product_sku', length: 191)->notNull();
                $table->string(name: 'product_price', length: 191)->defaultValue(0.00);
                $table->string(name: 'product_currency')->defaultValue('USD');
                $table->string(name: 'product_purchase_url', length: 191);
                $table->integer(name: 'product_show_in_menu')->size(value: 'large')->defaultValue(0);
                $table->integer(name: 'product_show_in_search')->size(value: 'large')->defaultValue(0);
                $table->string(name: 'product_featured_image', length: 191);
                $table->string(name: 'product_status', length: 36)->notNull()->defaultValue('draft');
                $table->string(name: 'product_created', length: 191);
                $table->dateTime(name: 'product_created_gmt');
                $table->string(name: 'product_published', length: 191);
                $table->dateTime(name: 'product_published_gmt');
                $table->string(name: 'product_modified', length: 191);
                $table->dateTime(name: 'product_modified_gmt');
                $table->index(['product_slug','product_sku'], $this->prefix . 'productIndex');

                $table->foreign('product_author', $this->prefix . 'productAuthor')
                    ->references($this->dfdb->basePrefix . 'user', 'user_id')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            });
    }

    /**
     * @throws Exception
     */
    private function elfinderFile(): void
    {
        $this->dfdb->schema()
            ->create(table: $this->prefix . 'elfinder_file', callback: function (CreateTable $table) {
                $table->integer(name: 'id')->size(value: 'normal')->unsigned()->notNull();
                $table->integer(name: 'parent_id')->size(value: 'normal')->unsigned()->notNull();
                $table->string(name: 'name')->notNull();
                $table->binary(name: 'content')->size(value: 'big')->notNull();
                $table->integer(name: 'size')->size(value: 'normal')->unsigned()->notNull()->defaultValue(value: 0);
                $table->integer(name: 'mtime')->size(value: 'normal')->unsigned()->notNull()->defaultValue(value: 0);
                $table->string(name: 'mime', length: 256)->notNull()->defaultValue(value: 'unknown');
                $table->string(name: 'read')->size(value: 'tiny')->notNull()->defaultValue(value: '1');
                $table->string(name: 'write')->size(value: 'tiny')->notNull()->defaultValue(value: '1');
                $table->string(name: 'locked')->size(value: 'tiny')->notNull()->defaultValue(value: '0');
                $table->string(name: 'hidden')->size(value: 'tiny')->notNull()->defaultValue(value: '0');
                $table->integer(name: 'width')->size(value: 'normal')->notNull()->defaultValue(value: 0);
                $table->integer(name: 'height')->size(value: 'normal')->notNull()->defaultValue(value: 0);
            });

        $sql = "INSERT INTO `{$this->prefix}elfinder_file` VALUES(1, 0, 'DATABASE', '', 0, 0, 'directory', '1', '1', '0', '0', 0, 0);";
        $this->dfdb->getConnection()->pdo->exec($sql);
    }

    /**
     * @throws Exception
     */
    private function elfinderTrash(): void
    {
        $this->dfdb->schema()
            ->create(table: $this->prefix . 'elfinder_trash', callback: function (CreateTable $table) {
                $table->integer(name: 'id')->size(value: 'normal')->unsigned()->notNull();
                $table->integer(name: 'parent_id')->size(value: 'normal')->unsigned()->notNull();
                $table->string(name: 'name')->notNull();
                $table->binary(name: 'content')->size(value: 'big')->notNull();
                $table->integer(name: 'size')->size(value: 'normal')->unsigned()->notNull()->defaultValue(value: 0);
                $table->integer(name: 'mtime')->size(value: 'normal')->unsigned()->notNull()->defaultValue(value: 0);
                $table->string(name: 'mime', length: 256)->notNull()->defaultValue(value: 'unknown');
                $table->string(name: 'read')->size(value: 'tiny')->notNull()->defaultValue(value: '1');
                $table->string(name: 'write')->size(value: 'tiny')->notNull()->defaultValue(value: '1');
                $table->string(name: 'locked')->size(value: 'tiny')->notNull()->defaultValue(value: '0');
                $table->string(name: 'hidden')->size(value: 'tiny')->notNull()->defaultValue(value: '0');
                $table->integer(name: 'width')->size(value: 'normal')->notNull()->defaultValue(value: 0);
                $table->integer(name: 'height')->size(value: 'normal')->notNull()->defaultValue(value: 0);
            });

        $sql = "INSERT INTO `{$this->prefix}elfinder_trash` VALUES(1, 0, 'DB Trash', '', 0, 0, 'directory', '1', '1', '0', '0', 0, 0);";
        $this->dfdb->getConnection()->pdo->exec($sql);
    }

    /**
     * @throws Exception
     */
    private function pages(): void
    {
        $this->dfdb->schema()
            ->create(table: $this->prefix . 'pages', callback: function (CreateTable $table) {
                $table->integer(name: 'id')->notNull()->autoIncrement();
                $table->string(name: 'name')->notNull();
                $table->string(name: 'show_in_nav')->notNull();
                $table->integer(name: 'nav_position')->notNull();
                $table->string(name: 'nav_type')->notNull();
                $table->string(name: 'layout')->notNull();
                $table->text(name: 'data')->size(value: 'big')->defaultValue(null);
                $table->text(name: 'page_attribute')->size(value: 'big');
            });
    }

    /**
     * @throws Exception
     */
    private function uploads(): void
    {
        $this->dfdb->schema()
            ->create(table: $this->prefix . 'uploads', callback: function (CreateTable $table) {
                $table->integer(name: 'id')->notNull()->autoIncrement();
                $table->string(name: 'public_id', length: 50)->notNull()->unique();
                $table->string(name: 'original_file', length: 512)->notNull();
                $table->string(name: 'mime_type', length: 50)->notNull();
                $table->string(name: 'server_file', length: 512)->notNull()->unique();
            });
    }

    /**
     * @throws Exception
     */
    private function pageTranslations(): void
    {
        $this->dfdb->schema()
            ->create(table: $this->prefix . 'page_translations', callback: function (CreateTable $table) {
                $table->integer(name: 'id')->notNull()->autoIncrement();
                $table->integer(name: 'page_id')->notNull();
                $table->string(name: 'locale', length: 50)->notNull();
                $table->string(name: 'title')->notNull();
                $table->string(name: 'meta_title')->notNull();
                $table->string(name: 'meta_description')->notNull();
                $table->string(name: 'route')->notNull();

                $table->unique(columns: ['page_id', 'locale'], name: $this->prefix . 'idx_page_translations');
                $table->foreign(columns: 'page_id', name: $this->prefix . 'fx_page_trans_pid')
                    ->references($this->prefix . 'pages', 'id')
                    ->onUpdate(action: 'cascade')
                    ->onDelete(action: 'cascade');
            });
    }

    /**
     * @throws Exception
     */
    private function settings(): void
    {
        $this->dfdb->schema()
            ->create(table: $this->prefix . 'settings', callback: function (CreateTable $table) {
                $table->integer(name: 'id')->notNull()->autoIncrement();
                $table->string(name: 'setting', length: 50)->notNull()->unique();
                $table->text(name: 'value')->size(value: 'medium')->notNull();
                $table->integer(name: 'is_array')->notNull();
            });
    }

    /**
     * @return void
     * @throws Exception
     */
    private function contentComment(): void
    {
        if (!$this->dfdb->schema()->hasTable(table: $this->prefix . 'content_comment')) {
            $this->dfdb->schema()->create($this->prefix . 'content_comment', function (CreateTable $table) {
                $table->string('comment_id', length: 36)
                    ->primary()
                    ->unique($this->prefix . 'contentCommentId');
                $table->string('content_id', length: 36)->notNull()->index();
                $table->string('user_id', length: 36)->index();
                $table->string('parent_id', length: 36)->index();
                $table->text('comment_body')->notNull();
                $table->string('comment_status', length: 36)->notNull()->defaultValue('open');
                $table->string('comment_type', length: 36)->notNull()->defaultValue('editorial');
                $table->text('selection_json')->size('big');
                $table->dateTime('created_at')->notNull();
                $table->dateTime('updated_at');

                $table->foreign(columns: 'content_id', name: $this->prefix . 'fx_content_comment_cid')
                    ->references($this->prefix . 'content', 'content_id')
                    ->onDelete(action: 'cascade')
                    ->onUpdate(action: 'cascade');

                $table->foreign(columns: 'user_id', name: $this->prefix . 'fx_content_comment_uid')
                    ->references($this->dfdb->basePrefix . 'user', 'user_id')
                    ->onDelete(action: 'set null')
                    ->onUpdate(action: 'cascade');

                $table->foreign(columns: 'parent_id', name: $this->prefix . 'fx_content_comment_pid')
                    ->references($this->prefix . 'content_comment', 'comment_id')
                    ->onDelete(action: 'cascade')
                    ->onUpdate(action: 'cascade');
            });
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    private function contentActivity(): void
    {
        if (!$this->dfdb->schema()->hasTable(table: $this->prefix . 'content_workflow_activity')) {
            $this->dfdb->schema()->create(
                    $this->prefix . 'content_workflow_activity',
                    function (CreateTable $table) {
                        $table->string('activity_id', length: 36)
                            ->primary()
                            ->unique($this->prefix . 'contentWorkflowActivityId');
                        $table->string('content_id', length: 36)->notNull()->index();
                        $table->string('user_id', length: 36)->index();
                        $table->string('activity_type', length: 50)->notNull()->index();
                        $table->string('from_status', length: 36);
                        $table->string('to_status', length: 36);
                        $table->text('message')->size('big');
                        $table->text('metadata')->size('big');
                        $table->dateTime('created_at')->notNull();

                        $table->foreign(columns: 'content_id', name: $this->prefix . 'fx_content_wactivtity_cid')
                            ->references($this->prefix . 'content', 'content_id')
                            ->onDelete(action: 'cascade')
                            ->onUpdate(action: 'cascade');

                        $table->foreign(columns: 'user_id', name: $this->prefix . 'fx_content_wactivtity_uid')
                            ->references($this->dfdb->basePrefix . 'user', 'user_id')
                            ->onDelete(action: 'set null')
                            ->onUpdate(action: 'cascade');
                    }
            );
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    private function contentNotification(): void
    {
        if (!$this->dfdb->schema()->hasTable(table: $this->prefix . 'content_notification')) {
            $this->dfdb->schema()->create(
                    $this->prefix . 'content_notification',
                    function (CreateTable $table) {
                        $table->string('notification_id', length: 36)
                            ->primary()
                            ->unique($this->prefix . 'contentNotificationId');
                        $table->string('content_id', length: 36)->notNull()->index();
                        $table->string('user_id', length: 36)->notNull()->index();
                        $table->string('activity_id', length: 36)->index();
                        $table->string('notification_type', length: 80)->notNull();
                        $table->string('title', length: 191)->notNull();
                        $table->text('body')->size('big');
                        $table->integer('is_read')->notNull()->defaultValue(0);
                        $table->dateTime('created_at')->notNull();
                        $table->dateTime('read_at');
                        $table->index(['user_id', 'is_read']);

                        $table->foreign(columns: 'content_id', name: $this->prefix . 'fx_content_notification_cid')
                            ->references($this->prefix . 'content', 'content_id')
                            ->onDelete(action: 'cascade')
                            ->onUpdate(action: 'cascade');

                        $table->foreign(columns: 'user_id', name: $this->prefix . 'fx_content_notification_uid')
                            ->references($this->dfdb->basePrefix . 'user', 'user_id')
                            ->onDelete(action: 'cascade')
                            ->onUpdate(action: 'cascade');

                        $table->foreign(columns: 'activity_id', name: $this->prefix . 'fx_content_notification_aid')
                            ->references($this->prefix . 'content_workflow_activity', 'activity_id')
                            ->onDelete(action: 'set null')
                            ->onUpdate(action: 'cascade');
                    }
            );
        }
    }
}
