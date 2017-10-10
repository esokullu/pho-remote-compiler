<?php 

require 'vendor/autoload.php';
include_once 'Processor.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$configuration = [
    'settings' => [
        'displayErrorDetails' => true,
    ],
];
$c = new \Slim\Container($configuration);
$app = new \Slim\App($c);

$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write(file_get_contents('description.html'));
});

$app->post('/', function (Request $request, Response $response) {
    $processor = new PhoProcessor($request, $response);
    $response = $processor->checkRequest();
    if ($response->getStatusCode() === 200) {
        $files = $request->getUploadedFiles();

        $response = $processor->unzipFile($files['file']);
        if ($response->getStatusCode() === 200){
            $processor->compile();
            $response = $processor->createZip();
        }
    }
    //$processor->clean();
    //header("Content-Type: application/zip");
    // header("Content-Disposition: attachment; filename=$file_name");
    // header("Content-Length: " . filesize($yourfile));

    return $response;
});
$app->run();