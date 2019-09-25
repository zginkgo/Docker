<?php

$pdo=new \PDO('mysql:host=172.17.0.4;port=3306;', 'root', 'xyz*#ss134');
$pdo->setAttribute(\PDO::ATTR_ORACLE_NULLS, true);
$pdo->query('set names uft8;');
$sqlstr="use mysql;";
$pdo->exec($sqlstr);
$res=$pdo->query('show tables;');
$arr=[];
while($r=$res->fetch(\PDO::FETCH_ASSOC)){
    print_r($r);
    array_push($arr, $r);
}
echo '<hr><pre>';
print_r($arr);

