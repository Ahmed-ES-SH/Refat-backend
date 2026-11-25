<?php

$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

/*
 * This file emulates Apache's "mod_rewrite" functionality for the built-in
 * PHP web server, so Laravel can be developed without a real web server.
 *
 * Only real files are served directly. A directory must fall through to the
 * application: `public/docs/api` exists (the exported OpenAPI document lives
 * there) and would otherwise shadow the `/docs/api` route of the API docs.
 */
if ($uri !== '/' && is_file($publicPath.$uri)) {
    return false;
}

$formattedDateTime = date('D M j H:i:s Y');

$requestMethod = $_SERVER['REQUEST_METHOD'];
$remoteAddress = $_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT'];

file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

require_once $publicPath.'/index.php';
