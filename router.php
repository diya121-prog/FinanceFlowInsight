<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('/^\/api\/(.+)\.php/', $uri, $matches)) {
    $apiFile = __DIR__ . '/api/' . $matches[1] . '.php';
    if (file_exists($apiFile)) {
        require $apiFile;
        return true;
    }
}

if ($uri === '/' || $uri === '/index.html') {
    require __DIR__ . '/public/index.html';
    return true;
}

if (preg_match('/\.(html|css|js|png|jpg|jpeg|gif|svg|ico)$/', $uri)) {
    $publicFile = __DIR__ . '/public' . $uri;
    if (file_exists($publicFile)) {
        return false;
    }
}

require __DIR__ . '/public/index.html';
return true;
