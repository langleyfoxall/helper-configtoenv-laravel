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

class ConfigToEnvCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'config:config-to-env {file}';

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
        $this->file = $this->argument('file');
        $this->envKeyPrefix = strtoupper(substr(basename($this->file), 0, -4));

        $this->validateFile();

        $code = file_get_contents($this->file);

        try {
            $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
            $originalAst = $parser->parse($code);
            $ast = $parser->parse($code);
        } catch (Error $e) {
            $this->error('Parse error: '.$e->getMessage());
            exit;
        }

        $this->recursiveWalkAndReplace($ast[0]->expr->items);

        file_put_contents($this->file, (new PrettyPrinter\Standard())->prettyPrintFile($ast));

        $this->info($this->file.' processed.');
    }

    private function validateFile()
    {
        if (!file_exists($this->file)) {
            $this->error($this->file.' does not exist.');
            exit;
        }

        if (!is_readable($this->file)) {
            $this->error($this->file.' is not readable. Check permissions.');
            exit;
        }

        if (!is_writeable($this->file)) {
            $this->error($this->file.' is not writeable. Check permissions.');
            exit;
        }
    }

    private function recursiveWalkAndReplace(&$astElement, $keys = [])
    {
        $factory = new BuilderFactory();

        if (is_array($astElement)) {
            foreach($astElement as $item) {
                $this->recursiveWalkAndReplace($item, $keys);
            }

        } else {
            
            switch(get_class($astElement)) {

                case ArrayItem::class:

                    switch(get_class($astElement->value)) {

                        case String_::class:
                        case LNumber::class:

                            $keys[] = $astElement->key->value;

                            $key = $this->envKeyPrefix.'_'.strtoupper(implode('_', $keys));
                            $value = $astElement->value->value;

                            $envFuncCall = $factory->funcCall('env', [$key, $value]);
                            $astElement->value = $envFuncCall;
                            break;


                        default:
                            $keys[] = $astElement->key->value;
                            $this->recursiveWalkAndReplace($astElement->value, $keys);
                            break;
                    }
                    break;

                case Array_::class:
                    $this->recursiveWalkAndReplace($astElement->items, $keys);
                    break;

                default:
                    break;
            }
        }
    }
}