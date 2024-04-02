<?php

ini_set('display_errors', 1);

define("TOKEN", json_decode(file_get_contents('config.json'), TRUE)['token']);
define("URL", json_decode(file_get_contents('config.json'), TRUE)['your_site_url']);
define("CONTEST_URL", json_decode(file_get_contents('config.json'), TRUE)['your_contest_url']);
define("PRODUCTS_ROWS", json_decode(file_get_contents('config.json'), TRUE)['product_rows']);
define("SOCIALS", json_decode(file_get_contents('config.json'), TRUE)['socials']);
define("BTN_TEXTS", json_decode(file_get_contents('config.json'), TRUE)['btns']);
define("BTNS_IMAGES", json_decode(file_get_contents('config.json'), TRUE)['btns_images']);

//$data = json_decode(file_get_contents('test.json'), TRUE);
$data = json_decode(file_get_contents('php://input'), TRUE);
if (!isset($data)) return;
//file_put_contents('data.txt', var_export($data, true));

//file_put_contents('data.txt', '$data: ' . print_r($data, 1) . "\n", FILE_APPEND);
try {
    $data = $data['callback_query'];
    $msg = mb_strtolower($data['data'], 'utf-8');
    sendToTelegram("deleteMessage", ["chat_id" => $data["message"]["chat"]["id"], "message_id" => $data["message"]["message_id"]]);
} catch (Exception) {
    $data = null;
    $msg = mb_strtolower($data["message"]["text"]);
}
if (!isset($data)) {
    $data = json_decode(file_get_contents('php://input'), TRUE);
    $data = $data['message'];
    $msg = mb_strtolower($data['text'], 'utf-8');
}
$back_buttons = [["text" => "Назад", "callback_data" => "fine"]];

function btns($m, $r = 0, $inline = FALSE, $callback_data = ['']): bool|string
{
    $btns = array();
    if ($inline === TRUE) $inline = "inline_";
    else $inline = "";
    $i = 1;
    foreach ($m as $index => $item) {
        $keys = array_keys($item);
        if ($i == $r) {
            $btns[] = array();
            $i = 1;
        }
        if (count($callback_data) < count($m)) $cbd = 'Назад';
        else $cbd = $callback_data[$index];
        $arr = array(
            "text" => $item["text"],
            "callback_data" => $cbd
        );
        if (end($keys) == "url") $arr["url"] = $item["url"];
        $btns[$i - 1][] = $arr;
        $i++;
    }
    var_dump($btns);
    return json_encode(array(
        $inline . 'keyboard' => $btns
    ));
}

function sendToTelegram(string $method, array $data): mixed
{
    try {
        $query = 'https://api.telegram.org/bot' . TOKEN . '/' . $method . '?' . http_build_query($data);
        echo 'Query: ' . $query . PHP_EOL;
        $res = file_get_contents($query);
        return (json_decode($res, 1) ? json_decode($res, 1) : $res);
    } catch (Exception $e) {
        var_dump($e->getTrace());
        return false;
    }
}

function extractedProducts(): array
{
    $html = file_get_contents("https://novonatum.com/product-category/");

    $dom = new DOMDocument();
    @ $dom->loadHTML($html);
    $finder = new DOMXPath($dom);

    $a = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' row products ')]");
    $a = $a->item(0)->getElementsByTagName('a');
    $aa = array();
    $bb = array();

    foreach ($a as $item) {
        $aa[] = $item->textContent;
        $bb[] = $item->attributes->getNamedItem('href')->textContent;
    }

    return [$aa, $bb];
}

switch ($msg) {
    case 'назад':
    case '/start':
        $btns = [
            ["text" => "Продукты"],
            ["text" => "Сайт"]
        ];
        if (count(SOCIALS) != 0) $btns[] = ["text" => "Социальные сети"];
        if (CONTEST_URL != "") $btns[] = ["text" => "Конкурс"];
        $img = BTNS_IMAGES["/start"];
        if (strlen($img) > 0) {
            $mthd = 'sendPhoto';
            $send_data = [
                'photo' => $img,
                'caption' => BTN_TEXTS[$msg],
                'reply_markup' => btns($btns, 3, TRUE, ['продукты', 'сайт', 'социальные сети', 'конкурс'])
            ];
        } else {
            $mthd = 'sendMessage';
            $send_data = [
                'text' => BTN_TEXTS[$msg],
                'reply_markup' => btns($btns, 3, TRUE, ['продукты', 'сайт', 'социальные сети', 'конкурс'])
            ];
        }
            break;

    case 'продукты':
        $btns = [["text" => "Назад"], ["text" => "БиоАмикус"], ["text" => "Колиф"]];
        $img = BTNS_IMAGES["продукты"];
        if (strlen($img) > 0) {
            $mthd = 'sendPhoto';
            $send_data = [
                'photo' => $img,
                'caption' => BTN_TEXTS['продукты'],
                'reply_markup' => btns($btns, inline: TRUE, callback_data: ['назад', 'биоамикус', 'колиф'])
            ];
        } else {
            $mthd = 'sendMessage';
            $send_data = [
                'text' => BTN_TEXTS['продукты'],
                'reply_markup' => btns($btns, inline: TRUE, callback_data: ['назад', 'биоамикус', 'колиф'])
            ];
        }
        break;
        
    case "биоамикус":
    case "колиф":
        $mthd = 'sendMessage';
        $products = extractedProducts();
        $btns = [["text" => "Назад"]];
        foreach ($products[0] as $index => $item)
            if (str_contains(mb_strtolower($item, 'utf8'), $msg)) {
                $btns[] = ["text" => $item, "url" => $products[1][$index]];
            }
        if (PRODUCTS_ROWS) $r = intdiv(count($products[1]), 2) + count($products[1]) % 2 + 2;
        else $r = count($products[1])+1;
        $send_data = [
            'text' => BTN_TEXTS["продукты"],
            'reply_markup' => btns(
                $btns,
                $r,
                TRUE,
                ['Назад']
            )
        ];
        break;

    case 'сайт':
        $btns = [
            ["text" => "Назад", "callback_data" => "fine"],
            ["text" => BTN_TEXTS["текст_кнопки_сайта"], "url" => URL]
        ];
        $img = BTNS_IMAGES["сайт"];
        if (strlen($img) > 0) {
            $mthd = 'sendPhoto';
            $send_data = [
                'photo' => $img,
                'caption' => BTN_TEXTS['сайт'],
                'reply_markup' => btns($btns, inline: TRUE)
            ];
        } else {
            $mthd = 'sendMessage';
            $send_data = [
                'text' => BTN_TEXTS['сайт'],
                'reply_markup' => btns($btns, inline: TRUE)
            ];
        }
        break;

    case 'социальные сети':
        $btns = [["text" => "Назад", "callback_data" => "fine"]];
        foreach (SOCIALS as $index => $item) $btns[] = ["text" => $index, "url" => $item];
        $img = BTNS_IMAGES["социальные сети"];
        if (strlen($img) > 0) {
            $mthd = 'sendPhoto';
            $send_data = [
                'photo' => $img,
                'caption' => BTN_TEXTS['социальные сети'],
                'reply_markup' => btns($btns, inline: TRUE)
            ];
        } else {
            $mthd = 'sendMessage';
            $send_data = [
                'text' => BTN_TEXTS['социальные сети'],
                'reply_markup' => btns($btns, inline: TRUE)
            ];
        }
        break;

    case 'конкурс':
        $btns = [
            ["text" => "Назад", "callback_data" => "fine"],
            ["text" => BTN_TEXTS["текст_кнопки_конкурса"], "url" => CONTEST_URL]
        ];
        $img = BTNS_IMAGES["конкурс"];
        if (strlen($img) > 0) {
            $mthd = 'sendPhoto';
            $send_data = [
                'photo' => $img,
                'caption' => BTN_TEXTS['конкурс'],
                'reply_markup' => btns($btns, inline: TRUE)
            ];
        } else {
            $mthd = 'sendMessage';
            $send_data = [
                'text' => BTN_TEXTS['конкурс'],
                'reply_markup' => btns($btns, inline: TRUE)
            ];
        }
        break;
}

if (isset($mthd)) {
    if (!isset($send_data['chat_id'])) $send_data['chat_id'] = $data['chat']['id'];
    if (!isset($send_data['chat_id'])) $send_data['chat_id'] = $data['message']['chat']['id'];
    sendToTelegram($mthd, $send_data);
}
