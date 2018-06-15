<?php

namespace LangleyFoxall\ConfigToEnv\Commands;

use Illuminate\Console\Command;
use PhpParser\Error;
use PhpParser\NodeDumper;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Arg;
use PhpParser\Node\Stmt\Expression;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Stmt\Return_;

class ConfigToEnvCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'config:config-to-env {files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Replaces all variables in a Laravel config file with calls to env()';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $globPattern = $this->argument('files');
        $files = glob($globPattern);

        if (!$files) {
            $this->error('No file(s) match '.$globPattern.'.');
            exit;
        }

        foreach($files as $file) {
            $this->validateFile($file);
            $this->processFile($file);
        }
    }

    private function processFile($file)
    {
        $envKeyPrefix = strtoupper(substr(basename($file), 0, -4));

        $code = file_get_contents($file);

        try {
            $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
            $originalAst = $parser->parse($code);
            $ast = $parser->parse($code);
        } catch (Error $e) {
            $this->error('Parse error: '.$e->getMessage());
            exit;
        }

        foreach($ast as $key => &$astElement) {
            if (get_class($astElement) == Return_::class) {
                $this->recursiveWalkAndReplace($envKeyPrefix, $astElement->expr->items);
            }
        }

        file_put_contents($file, (new PrettyPrinter\Standard())->prettyPrintFile($ast));

        $this->info($file.' processed.');
    }

    private function validateFile($file)
    {
        if (!file_exists($file)) {
            $this->error($file.' does not exist.');
            exit;
        }

        if (!is_readable($file)) {
            $this->error($file.' is not readable. Check permissions.');
            exit;
        }

        if (!is_writeable($file)) {
            $this->error($file.' is not writeable. Check permissions.');
            exit;
        }
    }

    private function recursiveWalkAndReplace($envKeyPrefix, &$astElement, $keys = [])
    {
        $factory = new BuilderFactory();

        if (is_array($astElement)) {
            foreach($astElement as $item) {
                $this->recursiveWalkAndReplace($envKeyPrefix, $item, $keys);
            }

        } else {
            
            switch(get_class($astElement)) {

                case ArrayItem::class:

                    switch(get_class($astElement->value)) {

                        case String_::class:
                        case LNumber::class:

                            $keys[] = $astElement->key->value;

                            $key = $envKeyPrefix.'_'.strtoupper(implode('_', $keys));
                            $value = $astElement->value->value;

                            $envFuncCall = $factory->funcCall('env', [$key, $value]);
                            $astElement->value = $envFuncCall;
                            break;


                        default:
                            $keys[] = $astElement->key->value;
                            $this->recursiveWalkAndReplace($envKeyPrefix, $astElement->value, $keys);
                            break;
                    }
                    break;

                case Array_::class:
                    $this->recursiveWalkAndReplace($envKeyPrefix, $astElement->items, $keys);
                    break;

                default:
                    break;
            }
        }
    }
}