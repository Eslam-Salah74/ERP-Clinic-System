<?php

namespace Modules\Inventory\Enums;

enum ItemUnitEnum: string
{
    case ML = 'ml';                   // مللي
    case GRAM = 'gram';               // جرام
    case PIECE = 'piece';             // عدد / قطعة
    case STRIP = 'strip';             // شريط[cite: 2]
    case BOX = 'box';                 // علبة
    case VIAL = 'vial';               // فايل (أمبول / زجاجة ميزو)[cite: 1]

}
