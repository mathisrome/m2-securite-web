<?php

enum CommandEnums: string
{
    case SALE_TOTAL = 'Total des ventes';
    case REFRESH_STOCK = 'Recalcul des stocks';

    public static function toFormChoices(): array
    {
        return [
            self::SALE_TOTAL->value => self::SALE_TOTAL,
            self::REFRESH_STOCK->value => self::REFRESH_STOCK,
        ];
    }
}