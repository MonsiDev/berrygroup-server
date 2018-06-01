<?php
  defined('WS_RUN') or die('ACCESS DENIED!');

  require __DIR__ . '/phpmailer/PHPMailerAutoload.php';

  class APP {

    private static $spletni;
    private static $rediska;
    private static $blackberry;
    private static $redberry;
    private static $broadway;
    private static $fastberry;
    private static $minutka;
    private static $tirex;

    private static $mailer;
    public static $adminEmail = '';

    public static function Init() {
      self::$mailer = new PHPMailer;
      self::$mailer->isSMTP();
      self::$mailer->Host = SMTP_HOST;
      self::$mailer->SMTPAuth = true;
      self::$mailer->Username = SMTP_USERNAME; // логин от вашей почты
      self::$mailer->Password = SMTP_PASSWORD; // пароль от почтового ящика
      self::$mailer->SMTPSecure = 'ssl';
      self::$mailer->Port = SMTP_PORT;
    }

    public static function Booking($key, $value, $connection) {
      if($key == 'booking') {
        if($value != '[]') {
          APP::sendBooking($value);
          $connection->send(json_encode(['status' => 'OK' ]));
        }
      }
    }

    public static function Category($key, $value, $connection) {
      if($key == 'category') {
        if(isset($value)) {
          $connection->send(json_encode(array_merge([
            'type' => 'category',
            'data' => APP::getCategory($value),
          ], APP::getSettings($value))));
        }
      }
    }

    public static function Goods($key, $param, $connection) {
      if($key == 'goods') {
        $connection->send(json_encode([
          'type' => 'goods',
          'data' => APP::getGoods($param[0],(int)$param[1])
        ]));
      }
    }

    public static function Delivery($key, $value, $connection) {
      if($key == 'delivery') {
        APP::sendDelivery($value);
        $connection->send(json_encode([
          'status' => 'OK'
        ]));
      }
    }

    public static function Stars($key, $param, $connection) {
      if($key == 'stars') {
        global $stars;
        if($param[1] != "true") {
          $stars->{$param[0]}->count += $param[1];
          $stars->{$param[0]}->people++;
        }
        file_put_contents(__DIR__ . '/stars.json', json_encode($stars));
        $connection->send(json_encode(round($stars->{$param[0]}->count / $stars->{$param[0]}->people, 0, PHP_ROUND_HALF_DOWN)));
      }
    }

    public static function PDOConnect($param) {
      switch($param) {
        case 'minutka' :
          return new PDO('mysql:host=localhost;dbname=' . DB_MINUTKA_NAME . ';charset=utf8', DB_MINUTKA_USERNAME, DB_MINUTKA_PASSWORD);
          break;
        case 'spletni':
          return new PDO('mysql:host=localhost;dbname=' . DB_SPLETNI_NAME . ';charset=utf8', DB_SPLETNI_USERNAME, DB_SPLETNI_PASSWORD);
          break;
        case 'rediska':
          return new PDO('mysql:host=localhost;dbname=' . DB_REDISKA_NAME . ';charset=utf8', DB_REDISKA_USERNAME, DB_REDISKA_PASSWORD);
          break;
        case 'blackberry':
          return new PDO('mysql:host=localhost;dbname=' . DB_BLACKBERRY_NAME . ';charset=utf8', DB_BLACKBERRY_USERNAME, DB_BLACKBERRY_PASSWORD);
          break;
        case 'fastberry':
          return new PDO('mysql:host=localhost;dbname=' . DB_FASTBERRY_NAME . ';charset=utf8', DB_FASTBERRY_USERNAME, DB_FASTBERRY_PASSWORD);
          break;
        case 'redberry':
          return new PDO('mysql:host=localhost;dbname=' . DB_REDBERRY_NAME . ';charset=utf8', DB_REDBERRY_USERNAME, DB_REDBERRY_PASSWORD);
          break;
        case 'broadway':
          return new PDO('mysql:host=localhost;dbname=' . DB_BROADWAY_NAME . ';charset=utf8', DB_BROADWAY_USERNAME, DB_BROADWAY_PASSWORD);
          break;
        case 'tirex':
          return new PDO('mysql:host=localhost;dbname=' . DB_TIREX_NAME . ';charset=utf8', DB_TIREX_USERNAME, DB_TIREX_PASSWORD);
          break;
      }
    }

    public static function getSettings($param) {
      $PDO = self::PDOConnect($param);
      if($PDO) {
        $sql = 'SELECT * FROM `settings` WHERE 1';
        $sth = $PDO->prepare($sql);
        $sth->execute();
        $data = $sth->fetch()['siteinfo'];
        if($data) {
          // $settings = preg_replace("/[^a-z0-9]+/i",'', $data['siteinfo']);
          $siteinfo = unserialize($data);
          $PDO = null;
          APP::$adminEmail = $siteinfo['ru']['siteinfo_adminemail'];
          return [
            'phone' => $siteinfo['ru']['siteinfo_mainphone'],
            'time' => $siteinfo['ru']['contacts']['schedule'],
            'address' => $siteinfo['ru']['siteinfo_address']
          ];
        }
      }
    }

    public static function countByCategory($categoryID, $PDO) {
      $sql = 'SELECT COUNT(*) AS _COUNT FROM `store_goods` g LEFT JOIN `store_goods_info` i ON i.good_id = g.id WHERE g.category_id = ? AND i.publish_status = 1';
      $sth = $PDO->prepare($sql);
      $sth->execute([$categoryID]);
      return $sth->fetch()['_COUNT'];
    }

    public static function getCategory($param) {
      $PDO = self::PDOConnect($param);
      if($PDO) {
        $sql = 'SELECT id,img,name FROM `store_category` c INNER JOIN `store_category_translate` t ON t.category_id = c.id WHERE t.publish_status=1 ORDER BY c.position DESC';
        $sth = $PDO->prepare($sql);
        $sth->execute();
        $fetchAll = $sth->fetchAll();
        $data = [];
        foreach($fetchAll as $row) {
          if(APP::countByCategory($row['id'], $PDO) > 0) {
            $data[] = $row;
          }
        }
        $PDO = null;
        return $data;
      }
      return false;
    }

    public static function getGoods($rest, $category_id) {
      $PDO = self::PDOConnect($rest);
      if($PDO) {
        $sql = 'SELECT * FROM `store_goods` LEFT JOIN `store_goods_info` ON store_goods_info.good_id = store_goods.id LEFT JOIN `store_goods_img` ON store_goods_img.item_id = store_goods.id WHERE category_id = ? AND store_goods_info.publish_status = 1';
        $sth = $PDO->prepare($sql);
        $sth->execute([$category_id]);

        $goods = $sth->fetchAll();
        // $i = 0;
        // $gCx = count($goods);
        // for($i = 0; $i < $gCx; $i++) {
        //   $goods[$i]['path'] = preg_replace("/.jpg$/i", '__420x420__.jpg', mb_strtolower($goods[$i]['path']));
        // }

        $PDO = null;
        return $goods;
      }
      return false;
    }

    public static function _getTPL($data, $file = 'send_form.temp') {
      $search = [];
      $replace = [];
      $buf = file_get_contents(dirname(__FILE__) . '/temp/' . $file);
      foreach($data as $key => $val) {
        $search[] = "<:{$key}:>";
        $replace[] = strval($val);
      }
      return str_replace($search, $replace, $buf);
    }

    public static function sendDelivery($json) {
      $goods_tpl = '';
      $data = json_decode($json);
      foreach($data->goods as $good) {
        $goods_tpl .= self::_getTPL($good, 'good_form.temp');
      }

      self::$mailer->CharSet = 'UTF-8';
      self::$mailer->From = SMTP_USERNAME; // адрес почты, с которой идет отправка
      self::$mailer->FromName = SMTP_FROM_NAME; // имя отправителя
      self::$mailer->addAddress(SMTP_FROM_SEND, SMTP_FROM_NAME);
      self::$mailer->addAddress(self::$adminEmail, SMTP_FROM_NAME);
      self::$mailer->isHTML(true);
      self::$mailer->Subject = SMTP_SUBJECT_SEND;
      self::$mailer->Body =  self::_getTPL([
        'address' => $data->address,
        'delivery' => ($data->delivery === true) ? 'Да' : 'Нет' ,
        'phone' => $data->phone,
        'name' => $data->name,
        'total' => $data->totalPrice,
        'goods' =>  $goods_tpl
      ]);
      self::$mailer->send();
    }

    public static function sendBooking($json) {
      $data = json_decode($json, true);
      self::$mailer->CharSet = 'UTF-8';
      self::$mailer->From = SMTP_USERNAME; // адрес почты, с которой идет отправка
      self::$mailer->FromName = SMTP_FROM_NAME; // имя отправителя
      self::$mailer->addAddress(SMTP_FROM_SEND, SMTP_FROM_NAME);
      self::$mailer->isHTML(true);
      self::$mailer->Subject = SMTP_SUBJECT_SEND;
      self::$mailer->Body =  self::_getTPL($data, 'booking_form.temp');
      self::$mailer->send();
    }
  }
