<?php
// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/exact_audit_registry.php';
require __DIR__.'/exact_guard_proof.php';
require __DIR__.'/exact_source_coverage.php';
$root=dirname(__DIR__);
$inventory=require $root.'/config/exact_calculations.php';
$central=require $root.'/config/exact_calculations_system.php';
$parser=(new PhpParser\ParserFactory())->createForNewestSupportedVersion();
$printer=new PhpParser\PrettyPrinter\Standard();
$matches=[];$errors=[];$scanned=0;
$blade=new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem(),sys_get_temp_dir());
$business='/amount|price|quantity|subtotal|total|cost|stock|balance|igv|iva|igtf|discount|charge|retention|point|commission|profit|interest|payment|payable|fee|rate|precio|cantidad|importe|monto|saldo|costo|descuento|puntos|comision|merma/i';
$scan=function(string $source,string $file,int $lineOffset=-1,string $context='{file}',?string $original=null) use (&$matches,&$errors,$parser,$printer,$business): void {
    try {$nodes=$parser->parse($source) ?? [];}catch(PhpParser\Error $error){$errors[]=['file'=>$file,'line'=>$error->getStartLine()+$lineOffset,'message'=>$error->getRawMessage()];return;}
    $visitor=new class($matches,$printer,$file,$lineOffset,$context,$business,$source,$original) extends PhpParser\NodeVisitorAbstract {
        private array $frames=[];
        private array $positions=[];
        public function __construct(private array &$rows,private $printer,private string $file,private int $offset,private string $context,private string $business,private string $source,private ?string $original){}
        public function enterNode(PhpParser\Node $node){
            if ($node instanceof PhpParser\Node\Stmt\Class_ || $node instanceof PhpParser\Node\Stmt\ClassMethod || $node instanceof PhpParser\Node\Stmt\Function_) $this->frames[]=$node->name ? (string)$node->name : '{anonymous}';
            $operation=null;
            if ($node instanceof PhpParser\Node\ArrayItem && $node->key!==null && $node->value instanceof PhpParser\Node\Scalar\String_
                && preg_match('/^(?:float|double|real|decimal:[0-9]+)$/i',$node->value->value)) $operation='model_cast';
            elseif ($node instanceof PhpParser\Node\Expr\Cast\Double) $operation='float';
            elseif($node instanceof PhpParser\Node\Expr\Cast\Int_)$operation='integer_cast';
            elseif($node instanceof PhpParser\Node\Expr\UnaryMinus || $node instanceof PhpParser\Node\Expr\UnaryPlus)$operation='native_unary';
            elseif($node instanceof PhpParser\Node\Expr\PreInc || $node instanceof PhpParser\Node\Expr\PostInc || $node instanceof PhpParser\Node\Expr\PreDec || $node instanceof PhpParser\Node\Expr\PostDec)$operation='native_update';
            elseif($node instanceof PhpParser\Node\Expr\FuncCall && $node->name instanceof PhpParser\Node\Name){$name=strtolower($node->name->toString());if(in_array($name,['round','number_format','floatval','intval','floor','ceil','sprintf','printf','abs','min','max','pow','fmod','intdiv','bcadd','bcsub','bcmul','bcdiv','bcpow','bcmod','bcscale'],true))$operation=$name;}
            elseif($node instanceof PhpParser\Node\Expr\MethodCall || $node instanceof PhpParser\Node\Expr\StaticCall){
                $name=$node->name instanceof PhpParser\Node\Identifier ? strtolower($node->name->toString()) : '';
                if(in_array($name,['sum','avg','average','increment','decrement','toscale','represent','format','finalamount','quotient','toint','tofloat','tobiginteger'],true))$operation=$name;
                elseif(in_array($name,['decimal','double','float'],true) && preg_match('~/migrations/~i','/'.$this->file))$operation='schema_numeric_type';
                elseif(in_array($name,['raw','selectraw','whereraw','havingraw','orderbyraw'],true))$operation='sql_expression';
                elseif(in_array($name,['update','insert','upsert','updateorinsert','insertorignore'],true))$operation='bulk_write';
            }
            elseif($node instanceof PhpParser\Node\Expr\BinaryOp || $node instanceof PhpParser\Node\Expr\AssignOp){
                $kind=str_replace('_','',substr(strrchr(get_class($node),'\\'),1));
                if(in_array($kind,['Plus','Minus','Mul','Div','Mod','Pow','Equal','NotEqual','Identical','NotIdentical','Greater','GreaterOrEqual','Smaller','SmallerOrEqual','Spaceship'],true))$operation='native_'.$kind;
            }
            elseif($node instanceof PhpParser\Node\Scalar\String_ && preg_match('/\b(SUM|AVG|ROUND|TRUNCATE|FLOOR|CEIL|DECIMAL)\s*\(/i',$node->value))$operation='sql_expression';
            elseif($node instanceof PhpParser\Node\Identifier && strtolower($node->name)==='float')$operation='float_signature';
            if(!$operation)return null;
            $text=$node instanceof PhpParser\Node\ArrayItem ? $this->printer->prettyPrintExpr($node->key).' => '.$this->printer->prettyPrintExpr($node->value)
                : ($node instanceof PhpParser\Node\Expr ? $this->printer->prettyPrintExpr($node) : (string)$node);
            $line=$node->getStartLine()+$this->offset;
            if($this->original!==null) {
                $fragment=substr($this->source,$node->getStartFilePos(),$node->getEndFilePos()-$node->getStartFilePos()+1);
                $parts=preg_split('/\\s+/',trim($fragment));
                $pattern='~'.implode('\\s*',array_map(fn($v)=>preg_quote($v,'~'),$parts)).'~';
                $position=$this->positions[$fragment] ?? 0;
                if(!preg_match($pattern,$this->original,$hit,PREG_OFFSET_CAPTURE,$position))return null;
                $this->positions[$fragment]=$hit[0][1]+strlen($hit[0][0]);
                $line=substr_count(substr($this->original,0,$hit[0][1]),"\n")+1;
            }
            $this->rows[]=['file'=>$this->file,'line'=>$line,'symbol'=>implode('::',$this->frames) ?: $this->context,'operation'=>$operation,'expression'=>preg_replace('/\s+/',' ',trim($text)),'review'=>'consumer_or_boundary_review','technical_proof'=>exactAuditGuardProof($node)];
            return null;
        }
        public function leaveNode(PhpParser\Node $node){if($node instanceof PhpParser\Node\Stmt\Class_ || $node instanceof PhpParser\Node\Stmt\ClassMethod || $node instanceof PhpParser\Node\Stmt\Function_)array_pop($this->frames);return null;}
    };
    $traverser=new PhpParser\NodeTraverser();$traverser->addVisitor($visitor);$traverser->traverse($nodes);
};
$policy=json_decode(file_get_contents($root.'/config/exact_audit_sources.json'),true,512,JSON_THROW_ON_ERROR);
$expectedSources=exactAuditSourceFiles($root,$policy,'php');$scannedFiles=[];
foreach($expectedSources as $path){
    $source=file_get_contents($root.'/'.$path);$scanned++;$scannedFiles[]=$path;
    if(str_ends_with($path,'.blade.php')) {
        $original=preg_replace_callback('/\{\{--[\s\S]*?--\}\}/',fn($m)=>preg_replace('/[^\n]/',' ',$m[0]),$source);
        $scan($blade->compileString($source),$path,0,'{blade}',$original);
    } else $scan($source,$path,0);
}
$missingSources=exactAuditMissingSources($expectedSources,$scannedFiles);
$matches=exactAuditClassify($matches,$root);
$registryErrors=exactAuditRegistryDiagnostics($matches,$root,'php');
$pending=count(array_filter($matches,fn($row)=>$row['status']==='pending'));
$result=['contract_version'=>1,'tables'=>count($inventory),'columns'=>array_sum(array_map('count',$inventory)),'decimal_columns'=>$inventory,'system_decimal_columns'=>$central,'scanned_php_files'=>$scanned,'php_review'=>$matches,'parse_errors'=>$errors,'registry_errors'=>$registryErrors,'coverage_missing'=>$missingSources,'scanned_files'=>$scannedFiles,'source_policy'=>$policy,'pending'=>$pending];
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
if($errors || (in_array('--check',$argv,true) && ($pending || $registryErrors || $missingSources)))exit(1);
// ######## FIN AUDITORIA CALCULOS EXACTOS PRO9 ########
