<?php

declare(strict_types=1);

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\SettingsBundle;

use Novosga\Module\BaseModule;

class NovosgaSettingsBundle extends BaseModule
{
    public function getIconName(): string
    {
        return 'wrench';
    }

    public function getDisplayName(): string
    {
        return 'module.name';
    }

    public function getHomeRoute(): string
    {
        return 'novosga_settings_index';
    }
}
