<?php

/*
| Arabic messages for the validation rules this application actually uses.
| Any rule missing here falls back to the English file.
*/

return [
    'current_password' => 'كلمة المرور الحالية غير صحيحة.',
    'different' => 'يجب أن تختلف :attribute عن :other.',
    'after_or_equal' => 'يجب أن يكون :attribute تاريخاً مساوياً أو لاحقاً لـ :date.',
    'boolean' => 'يجب أن تكون قيمة :attribute صح أو خطأ.',
    'date' => ':attribute ليس تاريخاً صالحاً.',
    'date_format' => ':attribute لا يطابق الصيغة :format.',
    'email' => 'يجب أن يكون :attribute بريداً إلكترونياً صالحاً.',
    'exists' => ':attribute المحدد غير صالح.',
    'in' => ':attribute المحدد غير صالح.',
    'integer' => 'يجب أن يكون :attribute عدداً صحيحاً.',
    'max' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عنصر.',
        'file' => 'يجب ألا يتجاوز حجم :attribute :max كيلوبايت.',
        'numeric' => 'يجب ألا يكون :attribute أكبر من :max.',
        'string' => 'يجب ألا يتجاوز :attribute :max حرفاً.',
    ],
    'min' => [
        'array' => 'يجب أن يحتوي :attribute على :min عنصر على الأقل.',
        'file' => 'يجب ألا يقل حجم :attribute عن :min كيلوبايت.',
        'numeric' => 'يجب ألا يكون :attribute أقل من :min.',
        'string' => 'يجب ألا يقل :attribute عن :min أحرف.',
    ],
    'password' => [
        'letters' => 'يجب أن يحتوي :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن يحتوي :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن يحتوي :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن يحتوي :attribute على رمز واحد على الأقل.',
        'uncompromised' => ':attribute ظهر في تسريب بيانات. اختر قيمة أخرى.',
    ],
    'present' => 'يجب إرسال حقل :attribute.',
    'regex' => 'صيغة :attribute غير صالحة.',
    'required' => 'حقل :attribute مطلوب.',
    'string' => 'يجب أن يكون :attribute نصاً.',
    'timezone' => 'يجب أن يكون :attribute منطقة زمنية صالحة.',
    'unique' => ':attribute مستخدم من قبل.',

    'attributes' => [
        'currentPassword' => 'كلمة المرور الحالية',
        'nickname' => 'اللقب',
        'remindAt' => 'وقت التذكير',
        'threshold' => 'الرقم المطلوب',
        'image' => 'الصورة',
        'displayName' => 'الاسم',
        'surname' => 'اسم العائلة',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'token' => 'رمز الاستعادة',
        'title' => 'العنوان',
        'description' => 'التفاصيل',
        'body' => 'النص',
        'category' => 'التصنيف',
        'categoryId' => 'التصنيف',
        'periodType' => 'الفترة',
        'periodStart' => 'بداية الفترة',
        'periodEnd' => 'نهاية الفترة',
        'dueDate' => 'تاريخ الاستحقاق',
        'priority' => 'الأولوية',
        'status' => 'الحالة',
        'targetValue' => 'القيمة المستهدفة',
        'currentValue' => 'القيمة الحالية',
        'bio' => 'النبذة',
        'timezone' => 'المنطقة الزمنية',
        'language' => 'اللغة',
        'weeklyGoal' => 'الهدف الأسبوعي',
        'dailyFocusMinutes' => 'وقت التركيز اليومي',
        'avatarUrl' => 'الصورة الشخصية',
        'minutes' => 'الدقائق',
        'day' => 'اليوم',
        'date' => 'التاريخ',
        'role' => 'الصلاحية',
    ],

    'custom' => [
        'store' => ['unavailable' => 'هذا العنصر غير متاح حالياً.', 'owned' => 'تملك هذا العنصر بالفعل.', 'points' => 'نقاطك لا تكفي لشراء هذا العنصر.', 'notOwned' => 'اشترِ هذا العنصر أولاً.'],
        'habits' => [
            'limit' => 'لا يمكن إضافة أكثر من :max عادة.',
            'future' => 'لا يمكن تسجيل يوم لم يأتِ بعد.',
        ],
    ],
];
