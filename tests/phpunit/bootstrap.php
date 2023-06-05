<?php

declare(strict_types = 1);

use Symfony\Component\Filesystem\Filesystem;

// ensure a fresh cache when debug mode is disabled
(new Filesystem())->remove(__DIR__ . '/../../var/cache/dev');
