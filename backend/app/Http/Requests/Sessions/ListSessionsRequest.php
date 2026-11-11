<?php

namespace App\Http\Requests\Sessions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/** Période du planning : bornes incluses, 366 jours couverts au plus. */
class ListSessionsRequest extends FormRequest
{
    public const MAX_DAYS = 366;

    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            // Comparé à `from` dans after() : la règle after_or_equal plante si `from` est un tableau.
            'to' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $from = Carbon::parse($this->input('from'));
                $to = Carbon::parse($this->input('to'));
                if ($to->lt($from)) {
                    $validator->errors()->add('to', 'La fin de la période doit être après son début.');
                } elseif ($from->diffInDays($to) + 1 > self::MAX_DAYS) {
                    $validator->errors()->add('to', 'La période ne doit pas dépasser '.self::MAX_DAYS.' jours.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'from.required' => 'Indiquez le début de la période.',
            'to.required' => 'Indiquez la fin de la période.',
            'date_format' => 'La date doit être au format AAAA-MM-JJ.',
        ];
    }
}
