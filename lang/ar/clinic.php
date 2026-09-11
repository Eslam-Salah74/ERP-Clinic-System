<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ترجمة أسماء الصلاحيات (Permissions)
    |--------------------------------------------------------------------------
    | المفتاح = اسم الـ Permission في الداتابيز  |  القيمة = الترجمة للفرونت
    */
    'permissions' => [

        // ─── Auth ──────────────────────────────────────────────────────
        'read roles'    => 'عرض الأدوار',
        'show roles'    => 'عرض تفاصيل دور',
        'create roles'  => 'إضافة دور',
        'update roles'  => 'تعديل دور',
        'delete roles'  => 'حذف دور',

        'read staff'    => 'عرض الموظفين',
        'show staff'    => 'عرض تفاصيل موظف',
        'create staff'  => 'إضافة موظف',
        'update staff'  => 'تعديل موظف',
        'delete staff'  => 'حذف موظف',

        // ─── Setup ─────────────────────────────────────────────────────
        'read departments'   => 'عرض الأقسام',
        'show departments'   => 'عرض تفاصيل قسم',
        'create departments' => 'إضافة قسم',
        'update departments' => 'تعديل قسم',
        'delete departments' => 'حذف قسم',

        'read services'   => 'عرض الخدمات',
        'show services'   => 'عرض تفاصيل خدمة',
        'create services' => 'إضافة خدمة',
        'update services' => 'تعديل خدمة',
        'delete services' => 'حذف خدمة',

        'read settings'   => 'عرض الإعدادات',
        'show settings'   => 'عرض تفاصيل إعداد',
        'create settings' => 'إضافة إعداد',
        'update settings' => 'تعديل إعداد',
        'delete settings' => 'حذف إعداد',

        // ─── Inventory ─────────────────────────────────────────────────
        'read suppliers'   => 'عرض الموردين',
        'show suppliers'   => 'عرض تفاصيل مورد',
        'create suppliers' => 'إضافة مورد',
        'update suppliers' => 'تعديل مورد',
        'delete suppliers' => 'حذف مورد',

        'read items'   => 'عرض الأصناف',
        'show items'   => 'عرض تفاصيل صنف',
        'create items' => 'إضافة صنف',
        'update items' => 'تعديل صنف',
        'delete items' => 'حذف صنف',

        'read purchase_invoices'   => 'عرض فواتير المشتريات',
        'show purchase_invoices'   => 'عرض تفاصيل فاتورة مشتريات',
        'create purchase_invoices' => 'إضافة فاتورة مشتريات',
        'update purchase_invoices' => 'تعديل فاتورة مشتريات',
        'delete purchase_invoices' => 'حذف فاتورة مشتريات',

        // ─── Reception ─────────────────────────────────────────────────
        'read patients'   => 'عرض المرضى',
        'show patients'   => 'عرض تفاصيل مريض',
        'create patients' => 'إضافة مريض',
        'update patients' => 'تعديل مريض',
        'delete patients' => 'حذف مريض',

        'read appointments'   => 'عرض المواعيد',
        'show appointments'   => 'عرض تفاصيل موعد',
        'create appointments' => 'إضافة موعد',
        'update appointments' => 'تعديل موعد',
        'delete appointments' => 'حذف موعد',

        'read shifts'   => 'عرض الشيفتات',
        'show shifts'   => 'عرض تفاصيل شيفت',
        'create shifts' => 'إضافة شيفت',
        'update shifts' => 'تعديل شيفت',
        'delete shifts' => 'حذف شيفت',
        'manage shifts' => 'إدارة الشيفتات',


        'read invoices'   => 'عرض الفواتير',
        'show invoices'   => 'عرض تفاصيل فاتورة',
        'create invoices' => 'إضافة فاتورة',
        'update invoices' => 'تعديل فاتورة',
        'delete invoices' => 'حذف فاتورة',
    ],

    /*
    |--------------------------------------------------------------------------
    | ترجمة أسماء الحقول (Attributes) — تُستخدم في رسائل التحقق
    |--------------------------------------------------------------------------
    */
    'attributes' => [
        'name'             => 'الاسم',
        'email'            => 'البريد الإلكتروني',
        'phone'            => 'رقم الهاتف',
        'password'         => 'كلمة المرور',
        'type'             => 'النوع',
        'notes'            => 'ملاحظات',
        'is_active'        => 'الحالة',
        'department_id'    => 'القسم',
        'items'            => 'الأصناف',
        'role_id'          => 'الدور الوظيفي',
        'basic_salary'     => 'الراتب الأساسي',
        'allowances'       => 'البدلات',
        'achieved_target'  => 'تحقيق الهدف',
        'permissions'      => 'الصلاحيات',
        'price'            => 'السعر',
        'key'              => 'المفتاح',
        'value'            => 'القيمة',
        'display_name'     => 'اسم العرض',
        'unit'             => 'وحدة القياس',
        'selling_price'    => 'سعر البيع',
        'current_stock'    => 'الرصيد الحالي',
        'company_name'     => 'اسم الشركة',
        'supplier_id'      => 'المورد',
        'purchase_price'   => 'سعر الشراء',
        'quantity'         => 'الكمية',
        'item_id'          => 'الصنف',
        'patient_id'       => 'المريض',
        'doctor_id'        => 'الطبيب',
        'nurse_id'         => 'الممرض',
        'service_id'       => 'الخدمة',
        'appointment_id'   => 'الموعد',
        'appointment_date' => 'تاريخ الموعد',
        'visit_type'       => 'نوع الزيارة',
        'status'           => 'الحالة',
        'gender'           => 'الجنس',
        'age'              => 'العمر',
        'is_staff'         => 'موظف',
        'discount'         => 'الخصم',
        'payment_method'   => 'طريقة الدفع',
    ],
];

