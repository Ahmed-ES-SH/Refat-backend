<?php

return [

    'accepted' => 'يجب قبول :attribute.',
    'array' => 'يجب أن يكون :attribute مصفوفة.',
    'boolean' => 'يجب أن تكون قيمة :attribute صحيحة أو خاطئة.',
    'confirmed' => 'تأكيد :attribute غير متطابق.',
    'current_password' => 'كلمة المرور الحالية غير صحيحة.',
    'date' => 'يجب أن يكون :attribute تاريخًا صالحًا.',
    'after' => 'يجب أن يكون :attribute تاريخًا بعد :date.',
    'after_or_equal' => 'يجب أن يكون :attribute تاريخًا بعد أو يساوي :date.',
    'before' => 'يجب أن يكون :attribute تاريخًا قبل :date.',
    'before_or_equal' => 'يجب أن يكون :attribute تاريخًا قبل أو يساوي :date.',
    'email' => 'يجب أن يكون :attribute بريدًا إلكترونيًا صالحًا.',
    'enum' => 'قيمة :attribute المختارة غير صالحة.',
    'exists' => 'قيمة :attribute المختارة غير صالحة.',
    'in' => 'قيمة :attribute المختارة غير صالحة.',
    'integer' => 'يجب أن يكون :attribute عددًا صحيحًا.',
    'numeric' => 'يجب أن يكون :attribute رقمًا.',
    'string' => 'يجب أن يكون :attribute نصًا.',
    'url' => 'يجب أن يكون :attribute رابطًا صالحًا.',
    'same' => 'يجب أن يتطابق :attribute مع :other.',
    'present' => 'يجب أن يكون الحقل :attribute موجودًا.',
    'required' => 'حقل :attribute مطلوب.',
    'required_if' => 'حقل :attribute مطلوب عندما يكون :other هو :value.',
    'required_with' => 'حقل :attribute مطلوب عندما يكون :values موجودًا.',
    'unique' => 'قيمة :attribute مستخدمة بالفعل.',
    'file' => 'يجب أن يكون :attribute ملفًا.',
    'image' => 'يجب أن يكون :attribute صورة.',
    'mimes' => 'يجب أن يكون :attribute ملفًا من نوع: :values.',
    'digits' => 'يجب أن يتكون :attribute من :digits أرقام.',
    'regex' => 'تنسيق :attribute غير صالح.',

    'size' => [
        'array' => 'يجب أن يحتوي :attribute على :size عنصر.',
        'file' => 'يجب أن يكون حجم :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute :size.',
        'string' => 'يجب أن يتكون :attribute من :size حرفًا.',
    ],

    'between' => [
        'array' => 'يجب أن يحتوي :attribute على بين :min و :max عنصر.',
        'file' => 'يجب أن يكون حجم :attribute بين :min و :max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute بين :min و :max.',
        'string' => 'يجب أن يتكون :attribute من بين :min و :max حرفًا.',
    ],

    'max' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عنصر.',
        'file' => 'يجب ألا يزيد حجم :attribute على :max كيلوبايت.',
        'numeric' => 'يجب ألا تزيد قيمة :attribute على :max.',
        'string' => 'يجب ألا يزيد :attribute على :max حرفًا.',
    ],

    'min' => [
        'array' => 'يجب أن يحتوي :attribute على :min عنصر على الأقل.',
        'file' => 'يجب ألا يقل حجم :attribute عن :min كيلوبايت.',
        'numeric' => 'يجب ألا تقل قيمة :attribute عن :min.',
        'string' => 'يجب ألا يقل :attribute عن :min حرفًا.',
    ],

    'password' => [
        'letters' => 'يجب أن تحتوي :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن تحتوي :attribute على حرف كبير وحرف صغير.',
        'numbers' => 'يجب أن تحتوي :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن تحتوي :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'ظهرت قيمة :attribute في تسريب بيانات. يرجى اختيار قيمة أخرى.',
    ],

    'custom' => [],

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'phone' => 'رقم الجوال',
        'date_of_birth' => 'تاريخ الميلاد',
        'gender' => 'الجنس',
        'locale' => 'اللغة',
        'relation' => 'صلة القرابة',
        'remember' => 'تذكرني',
        'token' => 'الرمز',
    ],

];
