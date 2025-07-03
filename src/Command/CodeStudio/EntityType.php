<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\CodeStudio;

enum EntityType: string
{
    case Application = 'Application';
    case Codebase = 'Codebase';
}
