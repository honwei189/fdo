<?php
require __DIR__ . '/../vendor/autoload.php';

use honwei189\Flayer\Config as config;

config::set_path(__DIR__ . "/../");
config::load();

// $app = new honwei189\Flayer\Core;
$app = new honwei189\Flayer;
// $app->FDO()->connect();
// $app->bind("honwei189\\FDO\\SQL", "FDO");
$app->bind("honwei189\\FDO");

// $dbh = honwei189\Flayer\Container::get("FDO")->connect($config['mysql']);
// $dbh = $app->FDO()->connect( honwei189\config::get("database", "mysql") );

$dbh = $app->FDO()->connect(config::get("database", "mysql"));

foreach ($app->FDO()->q('SELECT * from users limit 2') as $row) {
    print_r($row);
}

$app->FDO()->from("users");
print_r($app->FDO()->where("userid='admin'")->get("id, userid, name"));

var_dump($app::FDO()->is_connected());
var_dump(honwei189\Flayer\Core::FDO()->is_connected());

var_dump($app->FDO()->is_connected());
foreach ($dbh->query('SELECT * from users limit 1') as $row) {
    //print_r($row);
}

var_dump(honwei189\Flayer\Container::get("FDO")->is_connected());

//$app->FDO()->fetch_mode(\PDO::FETCH_INTO);
$app->FDO()->fetch_mode();
$app->FDO()->set_encrypt_id(true);

$app->FDO()->set_table("users");
pre($app->FDO()->where("userid='admin'")->get("id, userid, name"));
pre($app->FDO()->users()->get("id, userid, name"));

class aaa
{
    public $id;
    public $m_name;
}

pre($app->FDO()->users()->set_encrypt_id(false)->fetch_mode()->limit(1)->find());

pre($app->FDO()->ml_tor_list()->set_encrypt_id(false)->fetch_mode(\PDO::FETCH_INTO, new aaa)->limit(1)->order_by("id", "desc")->find("id, m_name"));
pre($app->FDO()->ml_tor_list()->limit(1)->order_by("id", "desc")->find("id, m_name"));
