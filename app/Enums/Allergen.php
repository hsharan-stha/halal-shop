<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Allergens covered by Japanese food labelling standards (食品表示基準):
 * mandatory "specified raw materials" (特定原材料) and recommended ones
 * (特定原材料に準ずるもの).
 */
enum Allergen: string
{
    use HasLabel;

    case Shrimp = 'shrimp';
    case Crab = 'crab';
    case Walnut = 'walnut';
    case Wheat = 'wheat';
    case Buckwheat = 'buckwheat';
    case Egg = 'egg';
    case Milk = 'milk';
    case Peanut = 'peanut';

    case Almond = 'almond';
    case Abalone = 'abalone';
    case Squid = 'squid';
    case SalmonRoe = 'salmon_roe';
    case Orange = 'orange';
    case Cashew = 'cashew';
    case Kiwi = 'kiwi';
    case Beef = 'beef';
    case Sesame = 'sesame';
    case Salmon = 'salmon';
    case Mackerel = 'mackerel';
    case Soybean = 'soybean';
    case Chicken = 'chicken';
    case Banana = 'banana';
    case Pork = 'pork';
    case Macadamia = 'macadamia';
    case Peach = 'peach';
    case Yam = 'yam';
    case Apple = 'apple';
    case Gelatin = 'gelatin';

    public function isMandatory(): bool
    {
        return in_array($this, [
            self::Shrimp, self::Crab, self::Walnut, self::Wheat,
            self::Buckwheat, self::Egg, self::Milk, self::Peanut,
        ], true);
    }
}
