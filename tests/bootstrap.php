<?php

require __DIR__."/../vendor/autoload.php";

Bow\Configuration\Loader::configure(__DIR__.'/config');

Bow\Container\Actionner::configure([], []);
