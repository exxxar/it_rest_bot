<?php

namespace App\Http\Controllers;

use App\Conversations\LotteryConversation;
use App\Conversations\StartDataConversation;
use App\Conversations\VipConversation;
use BotMan\BotMan\BotMan;
use Illuminate\Http\Request;

class BotManController extends Controller
{
    /**
     * Place your BotMan logic here.
     */
    public function handle()
    {
        $botman = app('botman');

        $botman->listen();
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function tinker()
    {
        return view('tinker');
    }

    /**
     * Loaded through routes/botman.php
     * @param BotMan $bot
     */
    public function lotteryConversation(BotMan $bot)
    {
        $bot->startConversation(new LotteryConversation($bot));
    }

    public function vipConversation(BotMan $bot)
    {
        $bot->startConversation(new VipConversation($bot));
    }

    public function startDataConversation(BotMan $bot,$data)
    {
        $bot->startConversation(new StartDataConversation($bot,$data));
    }
}
