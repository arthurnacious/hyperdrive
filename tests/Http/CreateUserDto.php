<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http;

use Hyperdrive\Http\Dto;

class CreateUserDto extends Dto
{
    public string $name;
    public string $email;
    public ?string $phone = null;
}
