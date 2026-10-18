<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBusinessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'website_url' => ['required', 'string', 'url', 'max:500'],
        ];
    }

    /**
     * Additional validation against SSRF and private networks.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $url = (string) $this->input('website_url');

            if (empty($url)) {
                return;
            }

            $host = parse_url($url, PHP_URL_HOST);

            if (! $host) {
                $validator->errors()->add('website_url', 'Invalid website URL format.');

                return;
            }

            // Reject localhost and IP ranges known for SSRF abuse
            if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
                $validator->errors()->add('website_url', 'Localhost or loopback URLs cannot be registered.');

                return;
            }

            $ip = gethostbyname($host);
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                // If it resolves to private/reserved IP (allow unless strictly in production, or flag)
                if (app()->isProduction()) {
                    $validator->errors()->add('website_url', 'Internal or private IP addresses are not permitted.');
                }
            }
        });
    }
}
