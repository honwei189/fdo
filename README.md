# FDO

FDO - Fast database OOP / ORM 

FDO is a PHP based library to assist user to do DB create/edit/delete/search/find and etc... It's uses ORM method to save / update data into DB.

  - Easy to use
  - Fast to build DB operate and query to save coding time
  - OOP / ORM method
  - Compatitable with Laravel
  - Allows connect mutiple DB at the same time

## Example

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

honwei189\config::load();
$app = new honwei189\flayer;
$app->bind("honwei189\\fdo\\fdo");

$dbh = $app->fdo()->connect(honwei189\config::get("database", "mysql"));

foreach ($app->fdo()->q('SELECT * from users') as $row) {
    print_r($row);
}

$users = $app->fdo()->from("users"); // Get data from table -- users
print_r($users->where("userid='admin'")->get("id, userid, name"));

var_dump($app::fdo()->is_connected());
var_dump(honwei189\flayer::fdo()->is_connected());


var_dump($app->fdo()->is_connected());
foreach ($dbh->query('SELECT * from users') as $row) {
    print_r($row);
}

var_dump(honwei189\container::get("fdo")->is_connected());

$app->fdo()->fetch_mode(\PDO::FETCH_INTO);

$app->fdo()->set_table("users"); // Get data from table -- users
print_r($app->fdo()->where("userid='admin'")->get("id, userid, name"));
print_r($app->fdo()->users()->get("id, userid, name"));

class aaa
{
    public $id;
    public $m_name;
}

 
print_r($app->fdo()->users()->fetch_mode()->limit(20)->find());  // Get data from table -- users  with default fetch mode -- PDO::FETCH_LAZY

$list = $app->fdo()->history_logs(); // Get data from table -- history_logs
print_r($list->fetch_mode(\PDO::FETCH_INTO, new aaa)->limit(20)->order_by("id", "desc")->find("id, m_name"));
print_r($list->limit(20)->order_by("id", "desc")->cols("id, m_name")->find());
print_r($list->limit(20)->order_by("id", "desc")->cols(["id", "m_name"])->find());
```

### Installation

To use FDO, you are requires to install [`flayer`](https://github.com/honwei189/flayer.git)

```sh
$ composer require honwei189/fdo
```
or
```sh
$ git clone https://github.com/honwei189/flayer.git
$ git clone https://github.com/honwei189/fdo.git
```
