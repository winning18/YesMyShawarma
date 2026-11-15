<?php

namespace App\Http\Requests;

use App\Services\Modules\ModuleService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Letters, digits and dashes only — this becomes the literal
            // prefix of a customer- and staff-facing order reference
            // (OrderCreationService::generateReference()), so spaces or
            // punctuation that would look wrong printed on a receipt are
            // rejected outright rather than silently allowed.
            'order_reference_prefix_pos' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            'order_reference_prefix_web' => [$this->forModule(ModuleService::WEBSITE), 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            // A switched-off module's fields aren't on the form (ModuleService).
            'order_reference_prefix_bolt_food' => [$this->forModule(ModuleService::BOLT_FOOD), 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            'order_reference_prefix_whatsapp' => [$this->forModule(ModuleService::WHATSAPP_REWARD), 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            'order_reference_prefix_freebie' => [$this->forModule(ModuleService::FREEBIES), 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            // Cedis off the food of a WhatsApp group order (owner, 2026-11-14).
            'whatsapp_reward' => [$this->forModule(ModuleService::WHATSAPP_REWARD), 'numeric', 'min:0', 'max:1000'],
            // Delivery fees, in cedis (DeliveryFeeCalculator).
            'delivery_rate_per_km' => ['sometimes', $this->forModule(ModuleService::DELIVERY), 'numeric', 'min:0', 'max:1000'],
            'delivery_minimum_fee' => ['sometimes', $this->forModule(ModuleService::DELIVERY), 'numeric', 'min:0', 'max:1000'],
            'delivery_minimum_order' => ['sometimes', $this->forModule(ModuleService::DELIVERY), 'numeric', 'min:0', 'max:10000'],
        ];
    }

    private function forModule(string $module): string
    {
        return app(ModuleService::class)->enabled($module) ? 'required' : 'exclude';
    }
}
