<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

dispatch(require BASE_PATH . '/app/routes.php');