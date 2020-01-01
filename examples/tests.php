<?php
require __DIR__ . '/../vendor/autoload.php';

honwei189\config::load();
$app = new honwei189\flayer;
// $app->fdo()->connect();
$app->bind("honwei189\\fdo\\fdo");

// $dbh = honwei189\container::get("fdo")->connect($config['mysql']);
// $dbh = $app->fdo()->connect( honwei189\config::get("database", "mysql") );
$dbh = $app->fdo()->connect(honwei189\config::get("database", "mysql"));

foreach ($app->fdo()->q('SELECT * from users') as $row) {
    print_r($row);
}

$app->fdo()->from("users");
print_r($app->fdo()->where("userid='admin'")->get("id, userid, name"));

var_dump($app::fdo()->is_connected());
var_dump(honwei189\flayer::fdo()->is_connected());


var_dump($app->fdo()->is_connected());
foreach ($dbh->query('SELECT * from users') as $row) {
    print_r($row);
}

var_dump(honwei189\container::get("fdo")->is_connected());

$app->fdo()->fetch_mode(\PDO::FETCH_INTO);
$app->fdo()->set_encrypt_id(true);

$app->fdo()->set_table("users");
pre($app->fdo()->where("userid='admin'")->get("id, userid, name"));
pre($app->fdo()->users()->get("id, userid, name"));

class aaa
{
    public $id;
    public $m_name;
}


pre($app->fdo()->users()->set_encrypt_id(false)->fetch_mode()->limit(20)->find());

pre($app->fdo()->ml_tor_list()->set_encrypt_id(false)->fetch_mode(\PDO::FETCH_INTO, new aaa)->limit(20)->order_by("id", "desc")->find("id, m_name"));
pre($app->fdo()->ml_tor_list()->limit(20)->order_by("id", "desc")->find("id, m_name"));
