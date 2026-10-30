<?php
declare(strict_types=1);

class icms_db_criteria_Item
{
    public function __construct($field, $value = '') {}
    public function render() { return ''; }
}

class icms_db_criteria_Compo
{
    public array $items = [];
    public mixed $sort = null;
    public function add($item) { $this->items[] = $item; }
    public function setStart($start) {}
    public function setLimit($limit) {}
    public function setSort($sort) { $this->sort = $sort; }
    public function setOrder($order) {}
}

class TestDatabase
{
    public function quoteString(string $value): string
    {
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
    }
}

class TestObject
{
    public function getVars(): array
    {
        return ['id' => ['persistent' => true], 'title' => ['persistent' => true],
            'virtual' => ['persistent' => false]];
    }
}

class TestHandler
{
    public ?icms_db_criteria_Compo $criteria = null;
    public function create() { return new TestObject(); }
    public function get($id) { return new TestObject(); }
    public function getObjects($criteria) { $this->criteria = $criteria; return []; }
    public function insert($object) { return true; }
    public function delete($object) { return true; }
}

class TestModule
{
    public function getVar($name) { return $name === 'isactive' ? 1 : null; }
    public function getInfo($name) { return ['article']; }
}

class TestModuleHandler
{
    public function getByDirname($name) { return new TestModule(); }
}

class icms
{
    public static object $xoopsDB;
    public static function handler($name) { return new TestModuleHandler(); }
}

$testHandler = new TestHandler();
icms::$xoopsDB = new TestDatabase();
function icms_getModuleHandler($name, $module, $unused, $force) {
    global $testHandler;
    return $testHandler;
}

require_once __DIR__ . '/../class/MCPProvider.php';

function expectInvalid(callable $fn): void
{
    try {
        $fn();
    } catch (InvalidArgumentException $exception) {
        return;
    }
    throw new RuntimeException('Expected an invalid argument exception.');
}

$provider = new MCPProvider();
$base = ['module' => 'news', 'handler' => 'article'];
$call = static fn(array $params) => $provider->handle([
    'method' => 'listObjects', 'params' => array_merge($base, $params),
]);

$call(['filters' => ['title' => "O'Reilly\\books"], 'sort' => 'title']);
if ($testHandler->criteria->items[0]->render() !== "title = 'O\\'Reilly\\\\books'"
    || $testHandler->criteria->sort !== 'title') {
    throw new RuntimeException('Filter quoting or sorting failed.');
}
$call(['filters' => ['title' => null]]);
if ($testHandler->criteria->items[0]->render() !== 'title IS NULL') {
    throw new RuntimeException('Null filter failed.');
}
expectInvalid(static fn() => $call(['filters' => ["id) OR 1=1 -- " => 1]]));
expectInvalid(static fn() => $call(['sort' => 'id DESC, (SELECT 1)']));
expectInvalid(static fn() => $call(['sort' => 'virtual']));
expectInvalid(static fn() => $call(['filters' => ['missing' => 1]]));
expectInvalid(static fn() => $call(['filters' => ['id' => [1]]]));
expectInvalid(static fn() => $call(['module' => "news' OR 1=1 --"]));
expectInvalid(static fn() => $call(['handler' => '../article']));

echo "Criteria security checks passed.\n";
