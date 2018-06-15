<?php
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

require_once __DIR__.'/../vendor/autoload.php';

$configFile = __DIR__.'/../test-config-files/queue.php';

$configId = strtoupper(substr(basename($configFile), 0, -4));

$code = file_get_contents($configFile);

$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

try {
    $originalAst = $parser->parse($code);
    $ast = $parser->parse($code);
} catch (Error $error) {
    echo 'Parse error: '.$error->getMessage();
    echo PHP_EOL;
    exit;
}



$configArrayRoot = $ast[0]->expr->items;

recursiveWalkAndReplace($configArrayRoot);

$prettyPrinter = new PrettyPrinter\Standard;
$newConfigFileContent = $prettyPrinter->prettyPrintFile($ast);

file_put_contents($configFile.'_new', $newConfigFileContent);


function recursiveWalkAndReplace(&$astElement, $keys = [])
{
    global $configId;

    $factory = new BuilderFactory();

    if (is_array($astElement)) {
        foreach($astElement as $item) {
            recursiveWalkAndReplace($item, $keys);
        }

    } else {
        
        switch(get_class($astElement)) {

            case ArrayItem::class:

                switch(get_class($astElement->value)) {

                    case String_::class:
                    case LNumber::class:

                        $keys[] = $astElement->key->value;

                        $key = $configId.'_'.strtoupper(implode('_', $keys));
                        $value = $astElement->value->value;

                        echo 'Key: '.$key.', Value: '.$value;
                        echo PHP_EOL;

                        $envFuncCall = $factory->funcCall('env', [$key, $value]);
                        $astElement->value = $envFuncCall;
                        break;


                    default:
                        $keys[] = $astElement->key->value;
                        recursiveWalkAndReplace($astElement->value, $keys);
                        break;
                }
                break;

            case Array_::class:
                recursiveWalkAndReplace($astElement->items, $keys);
                break;

            default:
                break;
        }
    }
}