<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Tick :attribute to go on.',
    'accepted_if' => 'The :attribute field must be accepted when :other is :value.',
    'active_url' => 'The :attribute field must be a valid URL.',
    'after' => ':Attribute must be after :date. Choose a later date.',
    'after_or_equal' => ':Attribute must be :date or later. Choose a later date.',
    'alpha' => 'The :attribute field must only contain letters.',
    'alpha_dash' => 'The :attribute field must only contain letters, numbers, dashes, and underscores.',
    'alpha_num' => 'The :attribute field must only contain letters and numbers.',
    'any_of' => 'The :attribute field is invalid.',
    'array' => ':Attribute isn\'t in the right form. Refresh the page and try again.',
    'array_keys' => 'The :attribute field must only contain the following keys: :values.',
    'ascii' => 'The :attribute field must only contain single-byte alphanumeric characters and symbols.',
    'base64' => 'The :attribute field must be a valid Base64 string.',
    'before' => ':Attribute must be before :date. Choose an earlier date.',
    'before_or_equal' => ':Attribute must be :date or earlier. Choose an earlier date.',
    'between' => [
        'array' => 'Choose between :min and :max for :attribute.',
        'file' => 'The file must be between :min and :max KB.',
        'numeric' => ':Attribute must be between :min and :max.',
        'string' => ':Attribute must be :min to :max characters long.',
    ],
    'boolean' => ':Attribute must be yes or no. Refresh the page and try again.',
    'can' => 'The :attribute field contains an unauthorized value.',
    'confirmed' => 'The two :attribute entries don\'t match. Type them again, the same both times.',
    'contains' => 'The :attribute field is missing a required value.',
    'current_password' => 'The password is incorrect.',
    'date' => ':Attribute isn\'t a date. Choose a date from the calendar.',
    'date_equals' => 'The :attribute field must be a date equal to :date.',
    'date_format' => ':Attribute isn\'t written the right way. Use the format :format (for a time, e.g. 08:30).',
    'decimal' => 'The :attribute field must have :decimal decimal places.',
    'declined' => 'The :attribute field must be declined.',
    'declined_if' => 'The :attribute field must be declined when :other is :value.',
    'different' => ':Attribute must be different from :other.',
    'digits' => ':Attribute must be exactly :digits digits.',
    'digits_between' => ':Attribute must be :min to :max digits.',
    'dimensions' => 'The photo isn\'t the right size. Check the size shown above the upload and try another photo.',
    'distinct' => ':Attribute is listed twice. Remove the repeat.',
    'doesnt_contain' => 'The :attribute field must not contain any of the following: :values.',
    'doesnt_end_with' => 'The :attribute field must not end with one of the following: :values.',
    'doesnt_start_with' => 'The :attribute field must not start with one of the following: :values.',
    'email' => 'That doesn\'t look like an email address. Check for a missing @ or dot (e.g. name@example.com).',
    'encoding' => 'The :attribute field must be encoded in :encoding.',
    'ends_with' => 'The :attribute field must end with one of the following: :values.',
    'enum' => 'The selected :attribute is invalid.',
    'exists' => 'The chosen :attribute no longer exists. Refresh the page and choose again.',
    'extensions' => 'The :attribute field must have one of the following extensions: :values.',
    'file' => ':Attribute must be a file. Choose one and try again.',
    'filled' => 'The :attribute field must have a value.',
    'gt' => [
        'array' => 'The :attribute field must have more than :value items.',
        'file' => 'The :attribute field must be greater than :value kilobytes.',
        'numeric' => ':Attribute must be more than :value.',
        'string' => 'The :attribute field must be greater than :value characters.',
    ],
    'gte' => [
        'array' => 'The :attribute field must have :value items or more.',
        'file' => 'The :attribute field must be greater than or equal to :value kilobytes.',
        'numeric' => ':Attribute must be :value or more.',
        'string' => 'The :attribute field must be greater than or equal to :value characters.',
    ],
    'hex_color' => 'The :attribute field must be a valid hexadecimal color.',
    'image' => 'That file isn\'t a photo. Choose a JPG, PNG or WebP image.',
    'in' => 'Choose one of the options for :attribute.',
    'in_array' => 'The :attribute field must exist in :other.',
    'in_array_keys' => 'The :attribute field must contain at least one of the following keys: :values.',
    'integer' => ':Attribute must be a whole number, like 3.',
    'ip' => 'The :attribute field must be a valid IP address.',
    'ipv4' => 'The :attribute field must be a valid IPv4 address.',
    'ipv6' => 'The :attribute field must be a valid IPv6 address.',
    'json' => 'The :attribute field must be a valid JSON string.',
    'list' => 'The :attribute field must be a list.',
    'lowercase' => 'The :attribute field must be lowercase.',
    'lt' => [
        'array' => 'The :attribute field must have less than :value items.',
        'file' => 'The :attribute field must be less than :value kilobytes.',
        'numeric' => ':Attribute must be less than :value.',
        'string' => 'The :attribute field must be less than :value characters.',
    ],
    'lte' => [
        'array' => 'The :attribute field must not have more than :value items.',
        'file' => 'The :attribute field must be less than or equal to :value kilobytes.',
        'numeric' => ':Attribute must be :value or less.',
        'string' => 'The :attribute field must be less than or equal to :value characters.',
    ],
    'mac_address' => 'The :attribute field must be a valid MAC address.',
    'max' => [
        'array' => 'Choose at most :max for :attribute.',
        'file' => 'The file is too big: the most is :max KB. Make it smaller and try again.',
        'numeric' => ':Attribute can\'t be more than :max.',
        'string' => ':Attribute is too long: use at most :max characters.',
    ],
    'max_digits' => 'The :attribute field must not have more than :max digits.',
    'mimes' => 'That file type can\'t be used here. Choose a :values file.',
    'mimetypes' => 'That file type can\'t be used here. Choose a :values file.',
    'min' => [
        'array' => 'Choose at least :min for :attribute.',
        'file' => 'The file is too small: at least :min KB.',
        'numeric' => ':Attribute must be at least :min.',
        'string' => ':Attribute is too short: use at least :min characters.',
    ],
    'min_digits' => 'The :attribute field must have at least :min digits.',
    'missing' => 'The :attribute field must be missing.',
    'missing_if' => 'The :attribute field must be missing when :other is :value.',
    'missing_unless' => 'The :attribute field must be missing unless :other is :value.',
    'missing_with' => 'The :attribute field must be missing when :values is present.',
    'missing_with_all' => 'The :attribute field must be missing when :values are present.',
    'multiple_of' => 'The :attribute field must be a multiple of :value.',
    'not_in' => 'That :attribute can\'t be used. Choose another.',
    'not_regex' => 'The :attribute field format is invalid.',
    'numeric' => ':Attribute must be a number, like 25 or 25.50 (no letters or GH₵).',
    'password' => [
        'letters' => 'The password needs at least one letter.',
        'mixed' => 'The password needs at least one capital and one small letter.',
        'numbers' => 'The password needs at least one number.',
        'symbols' => 'The password needs at least one symbol, like ! or #.',
        'uncompromised' => 'This password has appeared in a data leak elsewhere, so it isn\'t safe. Choose a different one.',
    ],
    'present' => ':Attribute is missing. Refresh the page and try again.',
    'present_if' => 'The :attribute field must be present when :other is :value.',
    'present_unless' => 'The :attribute field must be present unless :other is :value.',
    'present_with' => 'The :attribute field must be present when :values is present.',
    'present_with_all' => 'The :attribute field must be present when :values are present.',
    'prohibited' => ':Attribute can\'t be filled in here. Clear it and try again.',
    'prohibited_if' => 'The :attribute field is prohibited when :other is :value.',
    'prohibited_if_accepted' => 'The :attribute field is prohibited when :other is accepted.',
    'prohibited_if_declined' => 'The :attribute field is prohibited when :other is declined.',
    'prohibited_unless' => 'The :attribute field is prohibited unless :other is in :values.',
    'prohibits' => 'The :attribute field prohibits :other from being present.',
    'regex' => ':Attribute isn\'t in the right format. Check it and try again.',
    'required' => ':Attribute is missing. Fill it in or choose one, then try again.',
    'required_array_keys' => 'The :attribute field must contain entries for: :values.',
    'required_if' => ':Attribute is needed here. Fill it in and try again.',
    'required_if_accepted' => 'The :attribute field is required when :other is accepted.',
    'required_if_declined' => 'The :attribute field is required when :other is declined.',
    'required_unless' => ':Attribute is needed here. Fill it in and try again.',
    'required_with' => ':Attribute is needed when :values is filled in.',
    'required_with_all' => ':Attribute is needed when :values are filled in.',
    'required_without' => ':Attribute is needed when :values is empty.',
    'required_without_all' => 'Fill in at least one of :attribute or :values.',
    'same' => ':Attribute and :other must match.',
    'size' => [
        'array' => 'The :attribute field must contain :size items.',
        'file' => 'The :attribute field must be :size kilobytes.',
        'numeric' => ':Attribute must be :size.',
        'string' => ':Attribute must be exactly :size characters.',
    ],
    'starts_with' => 'The :attribute field must start with one of the following: :values.',
    'string' => ':Attribute must be text.',
    'timezone' => 'The :attribute field must be a valid timezone.',
    'unique' => 'That :attribute is already used. Choose a different one.',
    'uploaded' => 'The file didn\'t arrive: it may be bigger than 4 MB. Make it smaller (sending it to yourself on WhatsApp shrinks it) and try again.',
    'uppercase' => 'The :attribute field must be uppercase.',
    'url' => ':Attribute must be a full web address, starting with https:// .',
    'ulid' => 'The :attribute field must be a valid ULID.',
    'uuid' => 'The :attribute field must be a valid UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    // Fields picked from a list, not typed (2026-11-24): "Choose", not "Fill it in".
    'custom' => [
        'theme' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'website_theme' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'payment_method' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'fulfilment_type' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'delivery_area_id' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'branch_id' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'category_id' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'role' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'expense_category_id' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'pos_mode' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'table_id' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'menu_item_id' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'delivery_fee_handling' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'freebie_recipient_type' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'type' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'status' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'modules' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'option_ids' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'kind' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'printer_id' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'terminal_id' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
        'rider_id' => [
            'required' => 'Choose :attribute, then try again.',
            'required_if' => 'Choose :attribute, then try again.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'base_price' => 'price',
        'branch_id' => 'branch',
        'category_id' => 'category',
        'menu_item_id' => 'item',
        'option_ids' => 'options',
        'lat' => 'latitude',
        'lng' => 'longitude',
        'opens_at' => 'opening time',
        'closes_at' => 'closing time',
        'phone' => 'phone number',
        'branch_phone' => 'branch phone number',
        'owner_email' => 'owner\'s email',
        'owner_name' => 'owner\'s name',
        'primary_colour' => 'main colour',
        'accent_colour' => 'second colour',
        'delivery_area_id' => 'delivery area',
        'payment_method' => 'payment method',
        'fulfilment_type' => 'pickup or delivery',
        'momo_transaction_id' => 'MoMo transaction ID',
        'staff_code' => 'Staff ID',
        'pin' => 'PIN',
        'image' => 'photo',
        'photo' => 'photo',
        'receipt' => 'receipt photo',
    ],

];
