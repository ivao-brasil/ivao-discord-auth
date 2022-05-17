<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | El following language lines contener solo the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'El :attribute debe ser aceptado.',
    'active_url' => 'El :attribute no es una URL valida.',
    'after' => 'El :attribute debe ser una fecha posterior una :date.',
    'after_or_equal' => 'El :attribute debe ser una fecha posterior o igual una :date.',
    'alpha' => 'El :attribute debe contener solo letras.',
    'alpha_dash' => 'El :attribute debe contener solo letras, numeros, guiones y guiones bajos.',
    'alpha_num' => 'El :attribute debe contener solo letras y numeros.',
    'array' => 'El :attribute debe ser un array.',
    'before' => 'El :attribute debe ser una fecha antes del :date.',
    'before_or_equal' => 'El :attribute debe ser una fecha antes o igual una :date.',
    'between' => [
        'numeric' => 'El :attribute debe ser entre :min y :max.',
        'file' => 'El :attribute debe ser entre :min y :max kilobytes.',
        'string' => 'El :attribute debe ser entre :min y :max caracteres.',
        'array' => 'El :attribute debe ser entre :min y :max items.',
    ],
    'boolean' => 'El campo :attribute debe ser true o false.',
    'confirmed' => 'La confirmacion de :attribute no coincide.',
    'date' => 'El :attribute no es una fecha valida.',
    'date_equals' => 'El :attribute debe ser una fecha igual una :date.',
    'date_format' => 'El :attribute no coincide con el formato :format.',
    'different' => 'El :attribute y :other debe ser diferentes.',
    'digits' => 'El :attribute debe ser :digits digitos.',
    'digits_between' => 'El :attribute debe ser entre :min y :max digitos.',
    'dimensions' => 'El :attribute tiene las dimensiones de la imagen invalidas.',
    'distinct' => 'El campo :attribute tiene un valor duplicado.',
    'email' => 'El :attribute debe ser una una direccion email valida.',
    'ends_with' => 'El :attribute debe terminar con uno de los siguientes: :values.',
    'exists' => 'El selected :attribute es invalido.',
    'file' => 'El :attribute debe ser un archivo.',
    'filled' => 'El :attribute field debe tener un valor.',
    'gt' => [
        'numeric' => 'El :attribute debe ser mayor que :value.',
        'file' => 'El :attribute debe ser mayor que :value kilobytes.',
        'string' => 'El :attribute debe ser mayor que :value caracteres.',
        'array' => 'El :attribute debe have more que :value items.',
    ],
    'gte' => [
        'numeric' => 'El :attribute debe ser mayor que o igual :value.',
        'file' => 'El :attribute debe ser mayor que o igual :value kilobytes.',
        'string' => 'El :attribute debe ser mayor que o igual :value caracteres.',
        'array' => 'El :attribute debe have :value items o more.',
    ],
    'image' => 'El :attribute debe ser an image.',
    'in' => 'El :attribute seleccionado es invalido.',
    'in_array' => 'El campo :attribute no existe en: :other.',
    'integer' => 'El :attribute debe ser un integer.',
    'ip' => 'El :attribute debe ser una valid IP address.',
    'ipv4' => 'El :attribute debe ser una valid IPv4 address.',
    'ipv6' => 'El :attribute debe ser una valid IPv6 address.',
    'json' => 'El :attribute debe ser una valid JSON string.',
    'lt' => [
        'numeric' => 'El :attribute debe ser menor que :value.',
        'file' => 'El :attribute debe ser menor que :value kilobytes.',
        'string' => 'El :attribute debe ser menor que :value caracteres.',
        'array' => 'El :attribute debe ser menor que :value items.',
    ],
    'lte' => [
        'numeric' => 'El :attribute debe ser menor que o igual :value.',
        'file' => 'El :attribute debe ser menor que o igual :value kilobytes.',
        'string' => 'El :attribute debe ser menor que o igual :value caracteres.',
        'array' => 'El :attribute debe not have more que :value items.',
    ],
    'max' => [
        'numeric' => 'El :attribute may not ser mayor que :max.',
        'file' => 'El :attribute may not ser mayor que :max kilobytes.',
        'string' => 'El :attribute may not ser mayor que :max caracteres.',
        'array' => 'El :attribute may not have more que :max items.',
    ],
    'mimes' => 'El :attribute debe ser un archivo de tipo: :values.',
    'mimetypes' => 'El :attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'numeric' => 'El :attribute debe ser de al menos :min.',
        'file' => 'El :attribute debe ser de al menos :min kilobytes.',
        'string' => 'El :attribute debe ser de al menos :min caracteres.',
        'array' => 'El :attribute debe have de al menos :min items.',
    ],
    'not_in' => 'El :attribute seleccionado es invalido.',
    'not_regex' => 'El formato del :attribute es invalido.',
    'numeric' => 'El :attribute debe ser un numero.',
    'password' => 'La contraseña es incorrecta.',
    'present' => 'El campo :attribute debe estar presente.',
    'regex' => 'El formato del :attribute es invalido.',
    'required' => 'El campo :attribute es obligatorio.',
    'required_if' => 'El :attribute field es obligatorio cuando :other es :value.',
    'required_unless' => 'El :attribute field es obligatorio salvo :other esta en :values.',
    'required_with' => 'El :attribute field es obligatorio cuando :values esta presente.',
    'required_with_all' => 'El :attribute field es obligatorio cuando :values estan presentes.',
    'required_without' => 'El :attribute field es obligatorio cuando :values no esta presente.',
    'required_without_all' => 'El campo :attribute es obligatorio cuando ninguno de los :values esta presente.',
    'same' => 'El :attribute y :other deben coincidir.',
    'size' => [
        'numeric' => 'El :attribute debe ser de :size.',
        'file' => 'El :attribute debe ser de :size kilobytes.',
        'string' => 'El :attribute debe ser de :size caracteres.',
        'array' => 'El :attribute debe contener solo :size items.',
    ],
    'starts_with' => 'El :attribute debe comenzar con uno de los siguientes: :values.',
    'string' => 'El :attribute debe ser un string.',
    'timezone' => 'El :attribute debe ser una zona valida.',
    'unique' => 'El :attribute ha sido ya usado.',
    'uploaded' => 'El :attribute no se ha podido subir.',
    'url' => 'El formato del :attribute es invalido.',
    'uuid' => 'El :attribute debe ser un UUID valido.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify una specific custom language line for una given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | El following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
