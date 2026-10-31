<?php
use Symfony\Component\Dotenv\Dotenv;
require dirname(__DIR__).'/vendor/autoload.php';
// Hard isolation: never inherit the Compose application's DATABASE_URL.
$vars=['APP_ENV'=>'test','DATABASE_URL'=>'mysql://genesis_test:isolated_test_only@database-test:3306/genesis_v2?serverVersion=11.8.0-MariaDB&charset=utf8mb4','MESSENGER_TRANSPORT_DSN'=>'in-memory://','MAILER_DSN'=>'null://null'];
foreach($vars as $key=>$value) { putenv($key.'='.$value);$_SERVER[$key]=$_ENV[$key]=$value; }
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
