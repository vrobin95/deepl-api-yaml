<?php

namespace Sample;


use DeepL\TextResult;
use DeepL\Translator;
use Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';

// Run via `php -S localhost:8080 app.php` from this directory.
class App
{
    private $htmlTemplate = '';

    private $authKey = '';

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ , ['.env', '.env.local', '.env.development', '.env.production'], false);
        $dotenv->load();

        $this->htmlTemplate = file_get_contents(__DIR__ . '/template.html');
        $this->authKey = $_ENV['DEEPL_AUTH_KEY'];
    }

    private function getRequestPath()
    {
        $requestUri = $_SERVER['REQUEST_URI'];
        return parse_url($requestUri, PHP_URL_PATH);
    }

    public function run()
    {
        $requestPath = $this->getRequestPath();
        switch ($requestPath) {
            case '/':
                echo $this->htmlTemplate;
                break;
            case '/api':
                $this->apiAction();
                break;
            default:
                $this->notFoundAction();
        }
    }

    /*
     * Using a given file from POST request, send it to DeepL API to translate it
     * After receiving the translated file, save it in the result folder
     */
    private function apiAction()
    {
        $translator = new Translator($this->authKey);

        $inputFile = $_FILES['file']['tmp_name'];
        $ext = 'txt';
        $outputFile = __DIR__ . '/result/' . basename($_FILES['file']['name'], '.' . $ext) . '_translated.tmp';

        if (file_exists($outputFile)) {
            unlink($outputFile);
        }

        $this->translateFile($inputFile, $outputFile, $translator);

        //rename .tmp to .txt
        rename($outputFile, str_replace('.tmp', '.txt', $outputFile));

        echo 'File translated successfully';
    }

    public function translateFile($inputFile, $outputFile, Translator $translator, $lang = 'en-US')
    {
        $inputFile = fopen($inputFile, 'r');
        $outputFile = fopen($outputFile, 'w');
        while (($line = fgets($inputFile)) !== false) {
            if (substr($line, 0, 1) === '#' || $line === '' || $line === '\r\n' || $line === '\r' || $line === '\n') {
                fwrite($outputFile, $line);
            }
            else {
                $parts = explode(': ', $line);

                $name = $parts[0];
                unset($parts[0]);

                $value = implode(': ', $parts);
                //removing \n at the end of the line if any
                $value = str_replace("\n", '', $value);

                $trad = $translator->translateText($value, 'FR', $lang, [
                    'formality' => 'prefer_more'
                ]);
                $tradText = $trad->text;

                //checking if the translation start by an " but not end by one
                if (substr($tradText, 0, 1) === '"' && substr($tradText, -1) !== '"') {

                    //if ending with '".'
                    if (substr($tradText, -2) === '".') {
                        //removing the last 2 characters
                        $tradText = substr($tradText, 0, -2);
                        $tradText .= '."';
                    } else {
                        //adding " at the end of the translation
                        $tradText .= '"';
                    }
                }

                fwrite($outputFile, $name . (!empty($tradText) ? ': ' . $tradText . PHP_EOL : ''));
            }
        }
        fclose($inputFile);
        fclose($outputFile);
    }

    private function notFoundAction()
    {
        http_response_code(404);
        echo 'Not found';
    }
}

// Eugh, blame Firebase::JWT for this.
error_reporting(E_ALL & ~E_DEPRECATED);

$app = new App();
$app->run();