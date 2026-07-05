<?php

namespace App\Domain\Equipment;

/**
 * Predefined camping/caravan packing lists, by locale. Shared between the web
 * (EquipmentController) and the mobile JSON API (EquipmentApiController).
 */
final class EquipmentPresets
{
    private const LISTS = [
        "fr" => [
            "Tente",
            "Sac de couchage",
            "Matelas gonflable",
            "Réchaud de camping",
            "Bouteille de gaz",
            "Glacière",
            "Lampe frontale",
            "Table pliante",
            "Chaises pliantes",
            "Trousse de premiers secours",
            "Kit de vaisselle",
            "Couteau multifonction",
            "Bâche de sol",
            "Câble électrique caravane",
            "Cales de roue",
            "Tuyau d'eau potable",
            "Produit vaisselle biodégradable",
            "Sacs poubelle",
            "Anti-moustiques",
            "Chargeur portable",
        ],
        "en" => [
            "Tent",
            "Sleeping bag",
            "Air mattress",
            "Camping stove",
            "Gas bottle",
            "Cooler",
            "Headlamp",
            "Folding table",
            "Folding chairs",
            "First aid kit",
            "Cookware set",
            "Multi-tool knife",
            "Ground sheet",
            "Caravan power cable",
            "Wheel chocks",
            "Fresh water hose",
            "Biodegradable dish soap",
            "Bin bags",
            "Insect repellent",
            "Power bank",
        ],
    ];

    /**
     * @return string[]
     */
    public static function forLocale(string $locale): array
    {
        return self::LISTS[$locale] ?? self::LISTS["fr"];
    }
}
