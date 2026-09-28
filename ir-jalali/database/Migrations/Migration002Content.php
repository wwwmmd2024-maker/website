<?php

declare(strict_types=1);

namespace IRJalali\Database\Migrations;

use IRJalali\Core\Database\Migration;
use IRJalali\Core\Database\Schema\Schema;

/**
 * Content engine: post types, posts, taxonomies, media, menus,
 * templates and custom fields.
 */
final class Migration002Content extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('post_types', function ($table): void {
            $table->id();
            $table->string('slug', 60);
            $table->string('name', 100);
            $table->string('icon', 60, true);
            $table->json('supports');
            $table->json('settings');
            $table->boolean('is_system', true);
            $table->timestamps();
            $table->unique('slug');
        });

        $schema->create('post_statuses', function ($table): void {
            $table->id();
            $table->string('slug', 30);
            $table->string('name', 60);
            $table->timestamps();
            $table->unique('slug');
        });

        $schema->create('posts', function ($table): void {
            $table->id();
            $table->uuid();
            $table->string('post_type', 60, false, 'post');
            $table->string('title', 255);
            $table->string('slug', 255);
            $table->text('excerpt');
            $table->longText('content');
            $table->longText('content_json');
            $table->string('status', 20, false, 'draft');
            $table->bigInteger('author_id', true, null);
            $table->bigInteger('parent_id', true, null);
            $table->integer('menu_order', false, 0);
            $table->string('featured_image', 255, true);
            $table->string('template', 120, true);
            $table->string('locale', 10, false, 'fa_IR');
            $table->dateTime('published_at');
            $table->timestamps();
            $table->softDeletes();
            $table->unique('post_type', 'slug');
            $table->unique('uuid');
            $table->index('post_type', 'status');
            $table->index('author_id');
            $table->index('published_at');
        });

        $schema->create('post_meta', function ($table): void {
            $table->id();
            $table->bigInteger('post_id');
            $table->string('key', 120);
            $table->longText('value');
            $table->index('post_id', 'key');
            $table->foreign('post_id', 'id', 'posts');
        });

        $schema->create('revisions', function ($table): void {
            $table->id();
            $table->string('entity_type', 40);
            $table->bigInteger('entity_id');
            $table->bigInteger('user_id', true, null);
            $table->string('title', 255, true);
            $table->longText('snapshot');
            $table->boolean('is_autosave', false);
            $table->dateTime('created_at', false);
            $table->index('entity_type', 'entity_id');
        });

        $schema->create('taxonomies', function ($table): void {
            $table->id();
            $table->string('slug', 60);
            $table->string('name', 100);
            $table->json('post_types');
            $table->boolean('hierarchical', false);
            $table->timestamps();
            $table->unique('slug');
        });

        $schema->create('terms', function ($table): void {
            $table->id();
            $table->bigInteger('taxonomy_id');
            $table->string('name', 150);
            $table->string('slug', 191);
            $table->bigInteger('parent_id', true, null);
            $table->text('description');
            $table->integer('count', false, 0);
            $table->timestamps();
            $table->unique('taxonomy_id', 'slug');
            $table->foreign('taxonomy_id', 'id', 'taxonomies');
        });

        $schema->create('term_relationships', function ($table): void {
            $table->id();
            $table->bigInteger('post_id');
            $table->bigInteger('term_id');
            $table->integer('ordering', false, 0);
            $table->unique('post_id', 'term_id');
            $table->foreign('post_id', 'id', 'posts');
            $table->foreign('term_id', 'id', 'terms');
        });

        $schema->create('media', function ($table): void {
            $table->id();
            $table->uuid();
            $table->string('filename', 191);
            $table->string('original_name', 191);
            $table->string('mime', 100);
            $table->string('extension', 12);
            $table->bigInteger('size_bytes', false, 0);
            $table->string('folder', 120, false, '/');
            $table->string('disk', 20, false, 'uploads');
            $table->integer('width', false, 0);
            $table->integer('height', false, 0);
            $table->string('alt', 255, true);
            $table->text('caption');
            $table->text('description');
            $table->bigInteger('uploaded_by', true, null);
            $table->timestamps();
            $table->softDeletes();
            $table->unique('uuid');
            $table->index('folder');
            $table->index('mime');
        });

        $schema->create('media_meta', function ($table): void {
            $table->id();
            $table->bigInteger('media_id');
            $table->string('key', 120);
            $table->longText('value');
            $table->index('media_id', 'key');
            $table->foreign('media_id', 'id', 'media');
        });

        $schema->create('menus', function ($table): void {
            $table->id();
            $table->string('slug', 80);
            $table->string('name', 120);
            $table->string('location', 60, false, 'primary');
            $table->timestamps();
            $table->unique('slug');
        });

        $schema->create('menu_items', function ($table): void {
            $table->id();
            $table->bigInteger('menu_id');
            $table->bigInteger('parent_id', true, null);
            $table->string('title', 150);
            $table->string('type', 30, false, 'custom');
            $table->string('url', 500, true);
            $table->string('reference_type', 40, true);
            $table->bigInteger('reference_id', true, null);
            $table->integer('ordering', false, 0);
            $table->string('target', 12, false, '_self');
            $table->string('css_class', 120, true);
            $table->timestamps();
            $table->index('menu_id', 'ordering');
            $table->foreign('menu_id', 'id', 'menus');
        });

        $schema->create('templates', function ($table): void {
            $table->id();
            $table->string('slug', 120);
            $table->string('name', 150);
            $table->string('type', 40, false, 'page');
            $table->longText('content_json');
            $table->boolean('is_default', false);
            $table->timestamps();
            $table->unique('slug');
        });

        $schema->create('template_parts', function ($table): void {
            $table->id();
            $table->bigInteger('template_id');
            $table->string('area', 40);
            $table->longText('content_json');
            $table->integer('ordering', false, 0);
            $table->timestamps();
            $table->index('template_id', 'area');
            $table->foreign('template_id', 'id', 'templates');
        });

        $schema->create('custom_field_groups', function ($table): void {
            $table->id();
            $table->string('title', 150);
            $table->json('location_rules');
            $table->integer('ordering', false, 0);
            $table->boolean('is_active', true);
            $table->timestamps();
        });

        $schema->create('custom_fields', function ($table): void {
            $table->id();
            $table->bigInteger('group_id');
            $table->string('key', 100);
            $table->string('label', 150);
            $table->string('type', 30);
            $table->json('settings');
            $table->integer('ordering', false, 0);
            $table->timestamps();
            $table->index('group_id', 'ordering');
            $table->unique('group_id', 'key');
            $table->foreign('group_id', 'id', 'custom_field_groups');
        });
    }

    public function down(Schema $schema): void
    {
        foreach ([
            'custom_fields', 'custom_field_groups', 'template_parts', 'templates',
            'menu_items', 'menus', 'media_meta', 'media', 'term_relationships',
            'terms', 'taxonomies', 'revisions', 'post_meta', 'posts',
            'post_statuses', 'post_types',
        ] as $table) {
            $schema->drop($table);
        }
    }
}
