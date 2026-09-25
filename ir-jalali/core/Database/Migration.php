<?php

declare(strict_types=1);

namespace IRJalali\Core\Database;

use IRJalali\Core\Database\Schema\Schema;

/**
 * Base class for every migration (core, theme, plugin or module).
 */
abstract class Migration
{
    abstract public function up(Schema $schema): void;

    abstract public function down(Schema $schema): void;
}
