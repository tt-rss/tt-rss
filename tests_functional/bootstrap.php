<?php
    set_include_path(dirname(__DIR__) ."/include" . PATH_SEPARATOR .
    get_include_path());

    putenv("IS_TESTING=true");
	putenv("TTRSS_LOG_DESTINATION=stdout");

    require_once "autoload.php";
