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

namespace Novosga\SettingsBundle\Dto;

final readonly class UpdateUsuarioBehaviorDto
{
    public function __construct(
        public ?bool $callTicketByService,
        public ?bool $callTicketOutOfOrder,
        public ?bool $changeTicketType,
    ) {
    }
}
