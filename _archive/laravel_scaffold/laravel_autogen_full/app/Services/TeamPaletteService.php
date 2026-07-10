<?php

namespace App\Services;

class TeamPaletteService
{
    // Predefined palettes (hex)
    protected static array $palettes = [
        ['primary'=>'#0033A0','secondary'=>'#FFD600','accent'=>'#E31837'], // Azul-Amarillo-Rojo (Caribeños)
        ['primary'=>'#0B3D91','secondary'=>'#FFD700','accent'=>'#C8102E'],
        ['primary'=>'#002D62','secondary'=>'#F5A623','accent'=>'#D0021B'],
        ['primary'=>'#003E51','secondary'=>'#00AEEF','accent'=>'#FF6A00'],
        ['primary'=>'#1C1C1C','secondary'=>'#C0A062','accent'=>'#A51C30'],
    ];

    public static function pick(): array
    {
        return self::$palettes[array_rand(self::$palettes)];
    }
}
