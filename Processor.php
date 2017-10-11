<?php

/*
 * This file is part of the Pho package.
 *
 * (c) Andrii Cherytsta <poratuk@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use \Pho\Compiler\Compiler as Compiler;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Symfony\Component\Filesystem\Filesystem;

class PhoProcessor
{
    private $request;
    private $response;
    private $verstion;
    private $extension = 'pgql';
    public $file       = '';
    private $unpacked  = 'unpacked';
    private $compiled  = 'compiled';
    private $zipped    = 'zipped';

    public function __construct(Request $request, Response $response)
    {
        $this->request  = $request;
        $this->response = $response;
        $this->file     = 'file_' . time();
        @mkdir($this->unpacked);
        @mkdir($this->compiled);
        @mkdir($this->zipped);
    }

    public function checkRequest(): Response
    {
/*    
    if ($this->request->getHeaderLine('Host') != 'pho-cli') {
            $this->sendError('Wrong server');
        }
*/
        if (strpos('application/zip', $this->request->getHeaderLine('Accept')) === -1
            || strpos('application/json', $this->request->getHeaderLine('Accept')) === -1) {
            $this->sendError('Wrong accepteble formats');
        }

        $files = $this->request->getUploadedFiles();
        if (count($files) == 0) {
            $this->sendError('Server not accept zip file');
        }

        if (!$files['file'] instanceof \Slim\Http\UploadedFile) {
            $this->sendError('Wrong file variable');
        }

        if ($files['file']->getClientMediaType() != 'application/zip') {
            $this->sendError('Sended file are not zip file');
        }
        $post = $this->request->getParsedBody();
        if (isset($post['extension'])) {
            $this->extension = $post['extension'];
        }

        return $this->response;
    }

    public function unzipFile($file): Response
    {
        @mkdir('./'.$this->unpacked . DIRECTORY_SEPARATOR . $this->file, 0775);
        copy($file->file, $this->unpacked . DIRECTORY_SEPARATOR . $this->file . '.zip');

        $zip   = new \ZipArchive;
        $error = $zip->open($this->unpacked . DIRECTORY_SEPARATOR . $this->file . '.zip');

        if ($error == true) {
            $error = $zip->extractTo("./".$this->unpacked . DIRECTORY_SEPARATOR . $this->file);
            if ($error != true)
            {
                $this->sendError('Can not open extract files');
            }

        } else {
            $this->sendError('Can not open zip file');
        }

        $zip->close();

        return $this->response;
    }

    public function compile()
    {
        /*$this->compiler = new Compiler();
        $this->processDir($this->unpacked . DIRECTORY_SEPARATOR . $this->file . DIRECTORY_SEPARATOR);
        $this->compiler->save($this->compiled . DIRECTORY_SEPARATOR . $this->file . DIRECTORY_SEPARATOR);

        return $this->response;
        */
        $ret = 0;
        $output = [];
        exec("php /opt/pho-cli/bin/pho.php build ".
             escapeshellarg($this->unpacked . DIRECTORY_SEPARATOR . $this->file . DIRECTORY_SEPARATOR).
             " ".
             escapeshellarg($this->compiled . DIRECTORY_SEPARATOR . $this->file . DIRECTORY_SEPARATOR).
             " pgql",
             $output,
             $ret
           );
        return $ret === 0;
    }

    public function createZip()
    {
        $zip   = new \ZipArchive;
        $error = $zip->open($this->zipped . DIRECTORY_SEPARATOR . $this->file . '.zip', \ZipArchive::CREATE);

        if ($error === true) {
            // Create recursive directory iterator
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->compiled . DIRECTORY_SEPARATOR . $this->file),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {

                if (!$file->isDir()) {

                    $filePath     = $file->getRealPath();
                    $relativePath = ltrim(str_replace($source, '', $filePath), DIRECTORY_SEPARATOR);

                    $zip->addFile($filePath, $relativePath);
                }
            }
        } else {
            $this->sendError('Can create zip file');
        }

        // var_dump('created zip = '.$this->zipped . DIRECTORY_SEPARATOR . $this->file . '.zip'.(file_exists($this->zipped . DIRECTORY_SEPARATOR . $this->file . '.zip') ?'TRUE':"FALSE"));
        $zip->close();
        return $this->response;
    }

    public function clean()
    {
        $fs = new Filesystem();
        $fs->remove($this->unpacked . DIRECTORY_SEPARATOR . $this->file);
        $fs->remove($this->compiled . DIRECTORY_SEPARATOR . $this->file);
    }

    private function sendError(string $message = 'Not Acceptable', int $code = 400)
    {
        $this->response = $this->response->withStatus($code);
        $this->response->getBody()->write($message);
        return $response;
    }

    private function processDir(string $source): void
    {
        $dir = scandir($source);
        foreach ($dir as $file) {
            if ($file[0] == ".") {
                // includes hidden, . and ..
                continue;
            } elseif (is_dir($source . DIRECTORY_SEPARATOR . $file)) {
                $this->processDir($source . DIRECTORY_SEPARATOR . $file);
            } elseif (substr($file, -1 * (strlen("." . $this->extension))) == "." . $this->extension) {
                $this->compiler->compile($source . DIRECTORY_SEPARATOR . $file);
            }
        }
    }
}
