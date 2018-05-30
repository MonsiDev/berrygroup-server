<?php
  define('WS_RUN', true);

  require_once __DIR__ . '/config.php';
  require_once __DIR__ . '/app.php';
  require_once __DIR__ . '/vendor/Autoloader.php';

  use Workerman\Worker;

  global $stars;
  $stars = json_decode(file_get_contents(__DIR__ . '/stars.json'));

  $ws_worker = new Worker("websocket://" . WS_IP . ":" . WS_PORT);
  $ws_worker->count = WS_COUNT;

  $ws_worker->onConnect = function($connection) {
    echo "New connection\r\n";
    APP::Init();
  };
  
  $ws_worker->onMessage = function($connection, $data) {
    $result = explode('=', $data);
    $msg = 'EMPTY';
    $param = explode(':', $result[1]);

    APP::Booking($result[0], $result[1], $connection);
    APP::Category($result[0], $result[1], $connection);
    APP::Goods($result[0], $param, $connection);
    APP::Delivery($result[0], $result[1], $connection);
    APP::Stars($result[0], $param, $connection);
  };

  $ws_worker->onClose = function($connection) {
    echo "Connection closed\n";
  };

  Worker::runAll();
