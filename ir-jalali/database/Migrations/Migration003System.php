<?php

declare(strict_types=1);

namespace IRJalali\Database\Migrations;

use IRJalali\Core\Database\Migration;
use IRJalali\Core\Database\Schema\Schema;

/**
 * Presentation & platform systems: blocks, widgets, themes, plugins,
 * forms, notifications, tokens, logs, redirects, SEO, scheduler, cache.
 */
final class Migration003System extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('blocks', function ($table): void {
            $table->id();
            $table->string('slug', 100);
            $table->string('name', 150);
            $table->string('category', 60, false, 'general');
            $table->string('source', 30, false, 'core');
            $table->string('source_slug', 100, true);
            $table->json('schema');
            $table->json('defaults');
            $table->boolean('is_active', true);
            $table->timestamps();
            $table->unique('slug');
        });

        $schema->create('block_instances', function ($table): void {
            $table->id();
            $table->uuid();
            $table->bigInteger('block_id', true, null);
            $table->string('entity_type', 40);
            $table->bigInteger('entity_id');
            $table->string('area', 40, false, 'content');
            $table->longText('data_json');
            $table->integer('ordering', false, 0);
            $table->timestamps();
            $table->index('entity_type', 'entity_id');
        });

        $schema->create('widgets', function ($table): void {
            $table->id();
            $table->string('slug', 100);
            $table->string('name', 150);
            $table->string('source', 30, false, 'core');
            $table->string('source_slug', 100, true);
            $table->json('schema');
            $table->boolean('is_active', true);
            $table->timestamps();
            $table->unique('slug');
        });

        $schema->create('widget_instances', function ($table): void {
            $table->id();
            $table->uuid();
            $table->bigInteger('widget_id', true, null);
            $table->string('sidebar', 60);
            $table->longText('data_json');
            $table->integer('ordering', false, 0);
            $table->timestamps();
            $table->index('sidebar', 'ordering');
        });

        $schema->create('themes', function ($table): void {
            $table->id();
            $table->string('slug', 100);
            $table->string('name', 150);
            $table->string('version', 20, false, '1.0.0');
            $table->string('author', 120, true);
            $table->string('screenshot', 255, true);
            $table->boolean('is_active', false);
            $table->timestamps();
            $table->unique('slug');
        });

        $schema->create('theme_settings', function ($table): void {
            $table->id();
            $table->bigInteger('theme_id');
            $table->string('key', 120);
            $table->longText('value');
            $table->timestamps();
            $table->unique('theme_id', 'key');
            $table->foreign('theme_id', 'id', 'themes');
        });

        $schema->create('plugins', function ($table): void {
            $table->id();
            $table->string('slug', 100);
            $table->string('name', 150);
            $table->string('version', 20, false, '1.0.0');
            $table->string('author', 120, true);
            $table->string('status', 20, false, 'inactive');
            $table->json('dependencies');
            $table->timestamps();
            $table->unique('slug');
        });

        $schema->create('plugin_settings', function ($table): void {
            $table->id();
            $table->bigInteger('plugin_id');
            $table->string('key', 120);
            $table->longText('value');
            $table->timestamps();
            $table->unique('plugin_id', 'key');
            $table->foreign('plugin_id', 'id', 'plugins');
        });

        $schema->create('forms', function ($table): void {
            $table->id();
            $table->string('title', 150);
            $table->string('slug', 120);
            $table->text('description');
            $table->json('settings');
            $table->boolean('is_active', true);
            $table->timestamps();
            $table->unique('slug');
        });

        $schema->create('form_fields', function ($table): void {
            $table->id();
            $table->bigInteger('form_id');
            $table->string('key', 100);
            $table->string('label', 150);
            $table->string('type', 30);
            $table->json('settings');
            $table->integer('ordering', false, 0);
            $table->timestamps();
            $table->index('form_id', 'ordering');
            $table->foreign('form_id', 'id', 'forms');
        });

        $schema->create('form_submissions', function ($table): void {
            $table->id();
            $table->bigInteger('form_id');
            $table->longText('data_json');
            $table->string('ip', 45, true);
            $table->string('user_agent', 255, true);
            $table->boolean('is_read', false);
            $table->dateTime('created_at', false);
            $table->index('form_id', 'created_at');
            $table->foreign('form_id', 'id', 'forms');
        });

        $schema->create('notifications', function ($table): void {
            $table->id();
            $table->uuid();
            $table->bigInteger('user_id', true, null);
            $table->string('type', 60);
            $table->string('title', 191);
            $table->text('body');
            $table->string('url', 500, true);
            $table->dateTime('read_at');
            $table->dateTime('created_at', false);
            $table->index('user_id', 'read_at');
        });

        $schema->create('sessions', function ($table): void {
            // Reserved for the database session driver (native sessions used in M1).
            $table->string('id', 128);
            $table->bigInteger('user_id', true, null);
            $table->string('ip', 45, true);
            $table->string('user_agent', 255, true);
            $table->longText('payload');
            $table->integer('last_activity', false, 0);
            $table->unique('id');
            $table->index('user_id');
        });

        $schema->create('api_tokens', function ($table): void {
            $table->id();
            $table->bigInteger('user_id');
            $table->string('name', 100);
            $table->string('token_hash', 64);
            $table->json('abilities');
            $table->dateTime('last_used_at');
            $table->dateTime('expires_at');
            $table->timestamps();
            $table->unique('token_hash');
            $table->index('user_id');
            $table->foreign('user_id', 'id', 'users');
        });

        $schema->create('logs', function ($table): void {
            $table->id();
            $table->string('channel', 40, false, 'app');
            $table->string('level', 12, false, 'info');
            $table->string('message', 500);
            $table->longText('context_json');
            $table->bigInteger('user_id', true, null);
            $table->string('ip', 45, true);
            $table->dateTime('created_at', false);
            $table->index('channel', 'created_at');
            $table->index('level');
        });

        $schema->create('audit_logs', function ($table): void {
            $table->id();
            $table->bigInteger('user_id', true, null);
            $table->string('action', 100);
            $table->string('entity_type', 60, true);
            $table->bigInteger('entity_id', true, null);
            $table->longText('before_json');
            $table->longText('after_json');
            $table->string('ip', 45, true);
            $table->dateTime('created_at', false);
            $table->index('user_id', 'created_at');
            $table->index('action');
        });

        $schema->create('security_logs', function ($table): void {
            $table->id();
            $table->string('event', 80);
            $table->bigInteger('user_id', true, null);
            $table->string('ip', 45, true);
            $table->string('user_agent', 255, true);
            $table->longText('details_json');
            $table->dateTime('created_at', false);
            $table->index('event', 'created_at');
        });

        $schema->create('redirects', function ($table): void {
            $table->id();
            $table->string('from_path', 500);
            $table->string('to_url', 500);
            $table->integer('status_code', false, 301);
            $table->integer('hits', false, 0);
            $table->boolean('is_active', true);
            $table->timestamps();
            $table->unique('from_path');
        });

        $schema->create('seo_meta', function ($table): void {
            $table->id();
            $table->string('entity_type', 40);
            $table->bigInteger('entity_id');
            $table->string('meta_title', 255, true);
            $table->string('meta_description', 500, true);
            $table->string('canonical_url', 500, true);
            $table->string('og_image', 255, true);
            $table->json('extra');
            $table->timestamps();
            $table->unique('entity_type', 'entity_id');
        });

        $schema->create('scheduled_tasks', function ($table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('hook', 120);
            $table->string('schedule', 30, false, 'hourly');
            $table->longText('payload_json');
            $table->dateTime('next_run_at', false);
            $table->dateTime('last_run_at');
            $table->boolean('is_active', true);
            $table->timestamps();
            $table->unique('hook');
            $table->index('next_run_at');
        });

        $schema->create('cache_entries', function ($table): void {
            // Reserved for the database cache driver (file driver used in M1).
            $table->string('key', 191);
            $table->longText('value');
            $table->integer('expires_at', false, 0);
            $table->unique('key');
        });
    }

    public function down(Schema $schema): void
    {
        foreach ([
            'cache_entries', 'scheduled_tasks', 'seo_meta', 'redirects',
            'security_logs', 'audit_logs', 'logs', 'api_tokens', 'sessions',
            'notifications', 'form_submissions', 'form_fields', 'forms',
            'plugin_settings', 'plugins', 'theme_settings', 'themes',
            'widget_instances', 'widgets', 'block_instances', 'blocks',
        ] as $table) {
            $schema->drop($table);
        }
    }
}
