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
	
	/**
	 * source: https://gist.github.com/toddsby/f98d82314259ec5483d8
	 */
	private  function zipData($source, $destination) {
		if (extension_loaded('zip')) {
			if (file_exists($source)) {
				$zip = new ZipArchive();
				if ($zip->open($destination, ZIPARCHIVE::CREATE)) {
					$source = realpath($source);
					if (is_dir($source)) {
						$iterator = new RecursiveDirectoryIterator($source);
						// skip dot files while iterating 
						$iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);
						$files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
						foreach ($files as $file) {
							$file = realpath($file);
							if (is_dir($file)) {
								$zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
							} else if (is_file($file)) {
								$zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
							}
						}
					} else if (is_file($source)) {
						$zip->addFromString(basename($source), file_get_contents($source));
					}
				}
				return $zip->close();
			}
		}
		return false;
	}

    public function createZip()
    {
		if(!$this->zipData(
			$this->compiled . DIRECTORY_SEPARATOR . $this->file,
			$this->zipped . DIRECTORY_SEPARATOR . $this->file . '.zip'
		)) {
			$this->sendError('Can create zip file');
		}
		return $this->response;
    }

    public function clean()
    {
        $fs = new Filesystem();
        $fs->remove($this->unpacked . DIRECTORY_SEPARATOR . $this->file);
        $fs->remove($this->unpacked . DIRECTORY_SEPARATOR . $this->file.".zip");
	$fs->remove($this->compiled . DIRECTORY_SEPARATOR . $this->file);
    }

	public function result() {
return $this->zipped . DIRECTORY_SEPARATOR . $this->file . '.zip';
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
