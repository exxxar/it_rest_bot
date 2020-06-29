<?php

use App\Http\Controllers\BotManController;
use App\Prize;
use App\Product;
use App\User;
use BotMan\BotMan\BotMan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

$botman = resolve('botman');

function createUser($bot)
{
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();
    $username = $telegramUser->getUsername();
    $lastName = $telegramUser->getLastName();
    $firstName = $telegramUser->getFirstName();

    $user = User::where("telegram_chat_id", $id)->first();
    if (is_null($user))
        $user = \App\User::create([
            'name' => $username ?? "$id",
            'email' => "$id@t.me",
            'password' => bcrypt($id),
            'fio_from_telegram' => "$firstName $lastName",
            'telegram_chat_id' => $id,
            'is_admin' => false,
            'is_vip' => false,
            'cashback_money' => 0,
            'phone' => '',
            'birthday' => '',
        ]);
    return $user;
}
function mainMenu($bot, $message)
{
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $user = User::where("telegram_chat_id",$id)->first();

    if (is_null($user))
        $user=createUser($bot);


    $keyboard = [];

    array_push($keyboard, ["\xF0\x9F\x8D\xB1Наши услуги"]);
    array_push($keyboard, ["\xE2\x9A\xA1Заявка на услугу"]);
    array_push($keyboard,["\xF0\x9F\x8E\xB0Розыгрыш"]);
    array_push($keyboard,["\xF0\x9F\x92\xADО Нас"]);

    if ($user->is_admin)
        array_push($keyboard,["\xE2\x9A\xA0Админ. статистика"]);

    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => $message,
            "parse_mode" => "Markdown",
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'one_time_keyboard' => false,
                'resize_keyboard' => true
            ])
        ]);
}
$botman->hears('.*Админ. статистика', function ($bot) {
    $users_in_bd = User::all()->count();
    $vip_in_bd = User::where("is_vip",true)->get()->count();

    $vip_in_bd_day = User::whereDate('updated_at', Carbon::today())
        ->where("is_vip",true)
        ->orderBy("id", "DESC")
        ->get()
        ->count();

    $users_in_bd_day = User::whereDate('created_at', Carbon::today())
        ->orderBy("id", "DESC")
        ->get()
        ->count();

    $message = sprintf("Всего пользователей в бд: %s\nВсего Заявок:%s\nПользователей за день:%s\nЗаявок за день:%s",
        $users_in_bd,
        $vip_in_bd,
        $users_in_bd_day,
        $vip_in_bd_day
    );

    $bot->reply($message);

})->stopsConversation();
$botman->hears(".*Заявка на услугу|/do_vip", BotManController::class . "@vipConversation")->stopsConversation();
$botman->hears('.*Розыгрыш', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $keybord = [
        [
            ['text' => "Условия розыгрыша и призы", 'url' => "https://telegra.ph/Rozygrysh-cennyh-prizov-06-23"]
        ],
        [
            ['text' => "Ввести код и начать", 'callback_data' => "/lottery"]
        ]
    ];
    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => "Розыгрыш призов",
            "parse_mode" => "Markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' =>
                    $keybord
            ])
        ]);
})->stopsConversation();
$botman->hears('.*О нас', function ($bot) {
    $bot->reply("https://telegra.ph/O-Komande-06-29");
})->stopsConversation();

$botman->hears("/start ([0-9a-zA-Z=]+)", BotManController::class . '@startDataConversation')->stopsConversation();
$botman->hears('/start', function ($bot) {
    createUser($bot);
    mainMenu($bot, 'Главное меню');
})->stopsConversation();
$botman->hears('.*Наши услуги', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $keyboard = [
        [
            ['text' => "\xF0\x9F\x93\x8BПодробнее об услугах", 'callback_data' => "/more_details"],
        ],
    ];

    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => "https://telegra.ph/Razrabotka-veb-sajtov-06-29",
            "parse_mode" => "Markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' =>
                    $keyboard
            ])
        ]);
});

$botman->hears('/more_details', function ($bot) {
    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $keyboard = [];

    $products = Product::all();

    foreach ($products as $product)
        array_push($keyboard,[
            ['text' => $product->title, 'url' => $product->article_url],
        ]);

    $bot->sendRequest("sendMessage",
        [
            "chat_id" => "$id",
            "text" => "*Наше портфолио*\n_Мы представляем вам все основные наши услуги. Однако есть и то что не вошло в этот перечень. Дополнительно информацию вы можете узнать отправив заявку на услугу!_",
            "parse_mode" => "Markdown",
            'reply_markup' => json_encode([
                'inline_keyboard' =>
                    $keyboard
            ])
        ]);
});

$botman->hears('/lottery', BotManController::class . '@lotteryConversation');
$botman->hears('/check_lottery_slot ([0-9]+)', function ($bot, $slotId) {


    $telegramUser = $bot->getUser();
    $id = $telegramUser->getId();

    $prize = Prize::find($slotId);

    $message = "*" . $prize->title . "*\n"
        . "_" . $prize->description . "_\n";


    $bot->sendRequest("sendPhoto",
        [
            "chat_id" => "$id",
            "photo" => $prize->image_url,
            "caption" => $message,
            "parse_mode" => "Markdown",
        ]);

    $user = User::where("telegram_chat_id", $id)->first();

    try {

        Telegram::sendMessage([
            'chat_id' => env("CHANNEL_ID"),
            'parse_mode' => 'Markdown',
            'text' => sprintf(($prize->type === 0?"Заявка на получение приза":"*Пользователь получил виртуальный приз*")."\nНомер телефона:_%s_\nПриз: [#%s] \"%s\"",
                $user->phone,
                $prize[0]->id,
                $prize[0]->title),
            'disable_notification' => 'false'
        ]);
    } catch (\Exception $e) {
        Log::info("Ошибка отправки заказа в канал!");
    }


});


