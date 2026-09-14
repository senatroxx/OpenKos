<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum AmenityIcon: string
{
    case Wifi = 'wifi';
    case Car = 'car';
    case Snowflake = 'snowflake';
    case Tv = 'tv';
    case Waves = 'waves';
    case Dumbbell = 'dumbbell';
    case ShowerHead = 'shower-head';
    case WashingMachine = 'washing-machine';
    case CookingPot = 'cooking-pot';
    case ShieldCheck = 'shield-check';
    case House = 'house';
    case HousePlus = 'house-plus';
    case Building = 'building';
    case Building2 = 'building-2';
    case Warehouse = 'warehouse';
    case DoorOpen = 'door-open';
    case DoorClosed = 'door-closed';
    case Map = 'map';
    case MapPin = 'map-pin';
    case MapPinHouse = 'map-pin-house';
    case KeyRound = 'key-round';
    case Landmark = 'landmark';
    case Store = 'store';
    case Bed = 'bed';
    case BedDouble = 'bed-double';
    case BedSingle = 'bed-single';
    case Sofa = 'sofa';
    case Armchair = 'armchair';
    case RockingChair = 'rocking-chair';
    case Lamp = 'lamp';
    case LampCeiling = 'lamp-ceiling';
    case Table2 = 'table-2';
    case BookOpen = 'book-open';
    case Bath = 'bath';
    case Toilet = 'toilet';
    case Droplets = 'droplets';
    case Droplet = 'droplet';
    case Accessibility = 'accessibility';
    case HandHelping = 'hand-helping';
    case Utensils = 'utensils';
    case UtensilsCrossed = 'utensils-crossed';
    case Refrigerator = 'refrigerator';
    case Microwave = 'microwave';
    case Coffee = 'coffee';
    case Wine = 'wine';
    case ChefHat = 'chef-hat';
    case AirVent = 'air-vent';
    case Fan = 'fan';
    case Thermometer = 'thermometer';
    case Lightbulb = 'lightbulb';
    case Plug = 'plug';
    case Zap = 'zap';
    case Flame = 'flame';
    case Sun = 'sun';
    case Wind = 'wind';
    case Cloud = 'cloud';
    case Router = 'router';
    case WifiHigh = 'wifi-high';
    case Bluetooth = 'bluetooth';
    case TvMinimal = 'tv-minimal';
    case Monitor = 'monitor';
    case Cable = 'cable';
    case EthernetPort = 'ethernet-port';
    case Phone = 'phone';
    case CircleParking = 'circle-parking';
    case Bike = 'bike';
    case Bus = 'bus';
    case BusFront = 'bus-front';
    case TrainFront = 'train-front';
    case TramFront = 'tram-front';
    case Shield = 'shield';
    case Lock = 'lock';
    case Cctv = 'cctv';
    case AlarmClock = 'alarm-clock';
    case BadgeCheck = 'badge-check';
    case Siren = 'siren';
    case FireExtinguisher = 'fire-extinguisher';
    case Vault = 'vault';
    case Baby = 'baby';
    case WavesLadder = 'waves-ladder';
    case Volleyball = 'volleyball';
    case Gamepad2 = 'gamepad-2';
    case FerrisWheel = 'ferris-wheel';
    case Trophy = 'trophy';
    case Music = 'music';
    case Puzzle = 'puzzle';
    case TentTree = 'tent-tree';
    case Trees = 'trees';
    case TreePine = 'tree-pine';
    case TreeDeciduous = 'tree-deciduous';
    case TreePalm = 'tree-palm';
    case Flower2 = 'flower-2';
    case Leaf = 'leaf';
    case Umbrella = 'umbrella';
    case Fence = 'fence';
    case Mountain = 'mountain';
    case Brush = 'brush';
    case SprayCan = 'spray-can';
    case Recycle = 'recycle';
    case Trash2 = 'trash-2';
    case PawPrint = 'paw-print';
    case Dog = 'dog';
    case Cat = 'cat';
    case Bird = 'bird';
    case ConciergeBell = 'concierge-bell';
    case HandPlatter = 'hand-platter';
    case GraduationCap = 'graduation-cap';
    case School = 'school';
    case Hammer = 'hammer';
    case Wrench = 'wrench';
    case Package = 'package';
    case Clock = 'clock';
    case CalendarCheck = 'calendar-check';
    case CircleCheck = 'circle-check';
    case CircleUser = 'circle-user';
    case Sparkles = 'sparkles';
    case Star = 'star';
    case Heart = 'heart';
    case Gift = 'gift';
    case Tag = 'tag';

    public function label(): string
    {
        return match ($this) {
            self::Wifi => 'Wi-Fi',
            self::Tv => 'TV',
            default => Str::headline($this->value),
        };
    }
}
