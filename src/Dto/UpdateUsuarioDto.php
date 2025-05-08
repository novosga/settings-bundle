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

use Symfony\Component\Validator\Constraints as Assert;

/**
 * UpdateUsuarioDto
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
final readonly class UpdateUsuarioDto
{
    public function __construct(
        public ?string $tipoAtendimento,
        #[Assert\Range(min: 1)]
        public ?int $local,
        #[Assert\Range(min: 1)]
        public ?int $numero,
    ) {
    }
}
