<?php

namespace Modules\Reception\Enums;

enum PaymentMethodEnum: string
{
    case CASH = 'cash';           // كاش
    case VISA = 'visa';           // بطاقة ائتمان / فيزا
    case WALLET = 'wallet';       // محافظ إلكترونية (فودافون كاش، إنستاباي، إلخ)
    case INSURANCE = 'insurance'; // تأمين طبي (لو المركز بيتعامل مع شركات تأمين)
}
