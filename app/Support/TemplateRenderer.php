<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Blade;

final class TemplateRenderer
{
    /**
     * Render a template with contact data substitution
     *
     * @param string $template Template string with {{variable}} placeholders
     * @param array<string, mixed> $contactData Contact data for substitution
     * @param bool $escapeHtml Whether to escape HTML in variables (for email body)
     * @return string Rendered template
     */
    public function render(string $template, array $contactData, bool $escapeHtml = true): string
    {
        $data = $this->prepareData($contactData, $escapeHtml);
        return Blade::render($template, $data);
    }

    /**
     * Prepare contact data for template rendering
     *
     * @param array<string, mixed> $contactData Raw contact data
     * @param bool $escapeHtml Whether to escape HTML
     * @return array<string, string> Prepared data with string values
     */
    private function prepareData(array $contactData, bool $escapeHtml): array
    {
        $prepared = [];

        foreach ($contactData as $key => $value) {
            // Convert to string, handle nulls
            $stringValue = $value !== null ? (string) $value : '';

            // Escape HTML if needed
            $prepared[$key] = $escapeHtml
                ? htmlspecialchars($stringValue, ENT_QUOTES, 'UTF-8')
                : $stringValue;
        }

        return $prepared;
    }

    /**
     * Extract contact data from database record for template rendering
     *
     * @param object $contact Contact database record
     * @return array<string, mixed> Flattened contact data
     */
    public function extractContactData(object $contact): array
    {
        $data = [
            'first_name' => $contact->first_name ?? '',
            'last_name' => $contact->last_name ?? '',
            'email' => $contact->email ?? '',
            'phone' => $contact->phone_e164 ?? '',
        ];

        // Merge custom attributes from JSONB column
        if (isset($contact->attributes)) {
            $attributes = is_string($contact->attributes)
                ? json_decode($contact->attributes, true)
                : (array) $contact->attributes;

            if (is_array($attributes)) {
                $data = array_merge($data, $attributes);
            }
        }

        return $data;
    }
}
