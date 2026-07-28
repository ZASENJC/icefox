<?php

define('__TYPECHO_ROOT_DIR__', __DIR__);

class Typecho_Db
{
    const LEFT_JOIN = 'LEFT JOIN';
    const SORT_ASC = 'ASC';
    const SORT_DESC = 'DESC';
}

class FakePinningAdapter
{
    public function quoteValue($value)
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}

class FakePinningDb
{
    public function getAdapter()
    {
        return new FakePinningAdapter();
    }
}

class FakePinningQuery
{
    public $calls = array();

    public function join($table, $condition, $type)
    {
        $this->calls[] = array('join', $table, $condition, $type);
        return $this;
    }

    public function cleanAttribute($name)
    {
        $this->calls[] = array('cleanAttribute', $name);
        return $this;
    }

    public function order($column, $direction)
    {
        $this->calls[] = array('order', $column, $direction);
        return $this;
    }
}

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

require dirname(__DIR__) . '/core/post-pinning.php';

$listArchive = (object) array('parameter' => (object) array('type' => 'category_page'));
$singleArchive = (object) array('parameter' => (object) array('type' => 'post'));

assertSameValue(true, icefoxShouldOrderPinnedPosts($listArchive), 'Category pagination must use pinned ordering');
assertSameValue(false, icefoxShouldOrderPinnedPosts($singleArchive), 'Single posts must not use pinned ordering');

$query = new FakePinningQuery();
icefoxApplyPinnedPostOrdering($query, new FakePinningDb());

assertSameValue(array(
    array(
        'join',
        'table.fields AS icefox_pin',
        "table.contents.cid = icefox_pin.cid AND icefox_pin.name = 'isTop' "
            . "AND (icefox_pin.int_value = 1 OR icefox_pin.str_value IN ('1', 'true', 'yes'))",
        Typecho_Db::LEFT_JOIN
    ),
    array('cleanAttribute', 'order'),
    array('order', 'icefox_pin.cid IS NULL', Typecho_Db::SORT_ASC),
    array('order', 'table.contents.created', Typecho_Db::SORT_DESC),
    array('order', 'table.contents.cid', Typecho_Db::SORT_DESC)
), $query->calls, 'Pinned ordering must be global, deterministic, and field-backed');

echo "Pinned-post query behavior passed\n";
