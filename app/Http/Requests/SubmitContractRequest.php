<?php

namespace App\Http\Requests;

use App\Services\Subscriptions\ContractService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Aceleasi reguli ca ajax_send() din simulator (SIM:949-980), plus limite de lungime. */
class SubmitContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'pet_name' => ['required', 'string', 'max:80'],
            'species' => ['required', Rule::in(['dog', 'cat'])],
            'sex' => ['required', Rule::in(['male', 'female'])],
            'dob' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:1990-01-01'],
            'breed' => ['required', 'string', 'max:80'],
            'breed_other' => ['nullable', 'required_if:breed,__other__', 'string', 'max:80'],
            'weight' => ['required', 'numeric', 'min:0.5', 'max:70'],
            'sterilized' => ['required', Rule::in(['da', 'nu'])],
            'chronic' => ['required', Rule::in(['da', 'nu'])],
            'chronic_text' => ['nullable', 'required_if:chronic,da', 'string', 'max:200'],
            'emergency' => ['required', Rule::in(['da', 'nu'])],
            'routine' => ['nullable', 'array'],
            'routine.*' => [Rule::in(['lunar', 'trimestrial', 'semestrial', 'anual', 'urgenta'])],
            'owner_name' => ['required', 'string', 'min:3', 'max:120'],
            'owner_email' => ['required', 'email:rfc', 'max:190'],
            'owner_phone' => ['required', 'string', 'min:6', 'max:30'],
            'owner_addr' => ['required', 'string', 'min:5', 'max:250'],
            'owner_cnp' => ['required', 'string', 'regex:/^[0-9]{13}$/'],
            'owner_ci_serie' => ['required', 'string', 'max:4'],
            'owner_ci_nr' => ['required', 'string', 'max:10'],
            'pet_microchip' => ['nullable', 'string', 'max:40'],
            'legal' => ['required', 'array'],
            'signature' => ['required', 'string', 'starts_with:'.ContractService::SIGNATURE_PREFIX, 'max:1400000'],
            'payment_period_months' => ['required', Rule::in([1, 6, 12])],
            'submission_token' => ['required', 'string', 'min:10', 'max:80'],
            'agree' => ['accepted'],
        ];

        foreach (ContractService::LEGAL_KEYS as $key) {
            $rules['legal.'.$key] = ['accepted'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'owner_cnp.regex' => 'CNP-ul trebuie să aibă exact 13 cifre.',
            'legal.*.accepted' => 'Bifează toate confirmările legale.',
            'agree.accepted' => 'Acceptă termenii și politica de confidențialitate.',
            'signature.required' => 'Semnează în chenar înainte de a continua.',
            'weight.min' => 'Greutatea este prea mică pentru un abonament.',
            'weight.max' => 'Greutatea depășește limita acceptată.',
            'breed_other.required_if' => 'Scrie rasa.',
            'chronic_text.required_if' => 'Scrie afecțiunea cronică.',
        ];
    }

    public function attributes(): array
    {
        return [
            'pet_name' => 'numele animăluțului', 'species' => 'specia', 'sex' => 'sexul', 'dob' => 'data nașterii', 'breed' => 'rasa',
            'weight' => 'greutatea', 'sterilized' => 'sterilizat/castrat', 'chronic' => 'afecțiunea cronică', 'emergency' => 'urgențele medicale',
            'owner_name' => 'numele proprietarului', 'owner_email' => 'emailul', 'owner_phone' => 'telefonul', 'owner_addr' => 'adresa',
            'owner_cnp' => 'CNP-ul', 'owner_ci_serie' => 'seria CI', 'owner_ci_nr' => 'numărul CI', 'signature' => 'semnătura',
        ];
    }
}
