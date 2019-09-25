<?php
$redis=new \Redis();
$redis->connect('172.17.0.5', '6379', 30);
$redis->set('redistest', 'this is redis test');
$res=$redis->get('redistest');
echo $res;
