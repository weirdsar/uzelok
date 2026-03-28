<?php

declare(strict_types=1);

namespace Uzelok\Core;

enum Brand: string
{
    case BATYA = 'batya';
    case BUY = 'buy';
    case VOLNA = 'volna';

    public function label(): string
    {
        return match ($this) {
            self::BATYA => 'БАТЯ',
            self::BUY => 'БУЙ',
            self::VOLNA => 'ВОЛНА',
        };
    }

    public function subtitle(): string
    {
        return match ($this) {
            self::BATYA => 'Хозтовары',
            self::BUY => 'Авто / Мото / Водный транспорт',
            self::VOLNA => 'Охота / Рыбалка / Туризм',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BATYA => '#d97706',
            self::BUY => '#2563eb',
            self::VOLNA => '#059669',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::BATYA => '🔧',
            self::BUY => '🚗',
            self::VOLNA => '🎣',
        };
    }
}
