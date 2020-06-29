<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static OptionOne()
 * @method static static OptionTwo()
 * @method static static OptionThree()
 */
final class OrderTypeEnum extends Enum
{
    const Landing =   0;
    const ChatBot =   1;
    const Shop = 2;
    const MobileApp = 3;
}
