<?php

namespace App\Conversations;

use App\CashBackHistory;
use App\OrderHistory;
use App\User;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class VipConversation extends Conversation
{
    protected $bot;
    protected $user;
    protected $tmp_phone;

    public function createUser()
    {
        $telegramUser = $this->bot->getUser();
        $id = $telegramUser->getId();
        $username = $telegramUser->getUsername();
        $lastName = $telegramUser->getLastName();
        $firstName = $telegramUser->getFirstName();

        $user = User::where("telegram_chat_id", $id)->first();
        if ($user == null)
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

    function mainMenu($message)
    {
        $telegramUser = $this->bot->getUser();
        $id = $telegramUser->getId();

        $user = User::where("telegram_chat_id", $id)->first();

        if (is_null($user))
            $user = $this->createUser();


        $keyboard = [];

        array_push($keyboard, ["\xF0\x9F\x8D\xB1Наши услуги"]);
        array_push($keyboard, ["\xE2\x9A\xA1Заявка на услугу"]);
        array_push($keyboard, ["\xF0\x9F\x8E\xB0Розыгрыш"]);
        array_push($keyboard, ["\xF0\x9F\x92\xADО Нас"]);

        if ($user->is_admin)
            array_push($keyboard, ["\xE2\x9A\xA0Админ. статистика"]);


        $this->bot->sendRequest("sendMessage",
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

    public function __construct($bot)
    {
        $telegramUser = $bot->getUser()->getId();
        $this->bot = $bot;
        $this->user = User::where("telegram_chat_id", $telegramUser)->first();

        if (is_null( $this->user))
            $this->user = $this->createUser();

        $this->tmp_phone = "";
    }


    public function askPhone()
    {
        $question = Question::create('Скажите мне свой телефонный номер в формате 071XXXXXXX')
            ->fallback('Спасибо что пообщался со мной:)!');

        try {
            $this->ask($question, function (Answer $answer) {
                $vowels = array("(", ")", "-", " ");

                $tmp_phone = $answer->getText();
                $tmp_phone = str_replace($vowels, "", $tmp_phone);
                if (strpos($tmp_phone, "+38") === false)
                    $tmp_phone = "+38" . $tmp_phone;


                $pattern = "/^\+380\d{3}\d{2}\d{2}\d{2}$/";
                if (preg_match($pattern, $tmp_phone) == 0) {
                    $this->bot->reply("Номер введен не верно...\n");
                    $this->askPhone();
                    return;
                } else {

                    if (is_null($this->user->phone) || strlen(trim($this->user->phone)) == 0) {
                        $this->user->phone = $tmp_phone;
                        $this->user->is_vip = true;
                        $this->user->save();
                    }

                    $this->tmp_phone = $tmp_phone;

                    $this->askOrderType();
                    return;

                }

            });
        } catch (\Exception $e) {
            $this->bot->reply("Упс... ошибка..." . $e->getMessage() . " " . $e->getLine());
        }
    }

    public function askOrderType()
    {

        $type_0 = $this->bot->userStorage()->get("type_0") ?? null;
        $type_1 = $this->bot->userStorage()->get("type_1") ?? null;
        $type_2 = $this->bot->userStorage()->get("type_2") ?? null;
        $type_3 = $this->bot->userStorage()->get("type_3") ?? null;
        $type_4 = $this->bot->userStorage()->get("type_4") ?? null;

        $question = Question::create('Выберите какие именно услуги Вас интересуют?')
            ->fallback('Unable to create a new database')
            ->callbackId('create_database')
            ->addButtons([
                Button::create("\xF0\x9F\x93\x9EПерезвоните мне!")->value('stop'),
                Button::create(is_null($type_0) ? 'Мне нужна реклама' : "\xE2\x9C\x85Мне нужна реклама")->value('type_0'),
                Button::create(is_null($type_1) ? 'Мне нужен чат-бот' : "\xE2\x9C\x85Мне нужен чат-бот")->value('type_1'),
                Button::create(is_null($type_2) ? 'Мне нужен веб-сайт' : "\xE2\x9C\x85Мне нужен веб-сайт")->value('type_2'),
                Button::create(is_null($type_3) ? 'Мне нужно мобильное приложение' : "\xE2\x9C\x85Мне нужно мобильное приложение")->value('type_3'),
                Button::create(is_null($type_4) ? 'Другое' : "\xE2\x9C\x85Другое")->value('type_4'),
                Button::create("\xF0\x9F\x9A\xA9Завершить выбор")->value('stop'),
            ]);

        $this->ask($question, function (Answer $answer) {
            // Detect if button was clicked:
            if ($answer->isInteractiveMessageReply()) {
                $selectedValue = $answer->getValue(); // will be either 'yes' or 'no'

                if ($selectedValue == "stop") {
                    $order_type = [
                        "type_0" => $this->bot->userStorage()->get("type_0") ?? 'Не выбрано',
                        "type_1" => $this->bot->userStorage()->get("type_1") ?? 'Не выбрано',
                        "type_2" => $this->bot->userStorage()->get("type_2") ?? 'Не выбрано',
                        "type_3" => $this->bot->userStorage()->get("type_3") ?? 'Не выбрано',
                        "type_4" => $this->bot->userStorage()->get("type_4") ?? 'Не выбрано',
                    ];

                    OrderHistory::create([
                        'user_id' => $this->user->id,
                        'order_type' => json_encode($order_type),
                        'phone' => $this->tmp_phone,
                    ]);

                    $this->bot->userStorage()->delete();
                    $this->mainMenu("Ваша заявка принята в рассмотрение");
                    return;
                }

                $type = $this->bot->userStorage()->get($selectedValue) ?? null;

                $this->bot->userStorage()->save([
                    "$selectedValue" => is_null($type) ? true : ($type == true ? null : true)
                ]);

                $this->askOrderType();

                return;
            }
        });
    }

    /**
     * Start the conversation.
     *
     * @return mixed
     */
    public function run()
    {
        $this->askPhone();
    }
}
