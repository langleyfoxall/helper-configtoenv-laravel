<?php

namespace LangleyFoxall\ConfigToEnv\Commands;

use App\User;
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
    protected $signature = 'configToEnv:apply {file}';

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
    public function __construct(string $file)
    {
        parent::__construct();

        $this->file = $file;
        $this->envKeyPrefix = strtoupper(substr(basename($configFile), 0, -4));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->validateFile();

        $code = file_get_contents($this->file);

        try {
            $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
            $originalAst = $parser->parse($code);
            $ast = $parser->parse($code);
        } catch (Error $e) {
            throw new \Exception('Parse error: '.$e->getMessage());
        }

        $this->recursiveWalkAndReplace($ast[0]->expr->items);

        file_put_contents($configFile, (new PrettyPrinter\Standard())->prettyPrintFile($ast));

    }

    private function validateFile()
    {
        if (!file_exists($this->file)) {
            throw new \Exception('Specified file does not exist.');
        }

        if (!is_readable($this->file)) {
            throw new \Exception('Specified file is not readable. Check permissions.');
        }

        if (!is_writeable($this->file)) {
            throw new \Exception('Specified file is not writeable. Check permissions.');
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

                            echo 'Key: '.$key.', Value: '.$value;
                            echo PHP_EOL;

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