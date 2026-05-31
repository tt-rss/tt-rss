<?php
    set_include_path(dirname(__DIR__) ."/include" . PATH_SEPARATOR .
    get_include_path());

    session_save_path("/tmp");

    require_once "autoload.php";

    require_once __DIR__ . "/IntegrationTestCase.php";
    require_once __DIR__ . "/DbTestCase.php";
    require_once __DIR__ . "/HandlerTestCase.php";

    // Force Config singleton to re-read the environment variables
    Config::get_instance()->reload();
