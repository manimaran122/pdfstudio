<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validation rules for a tool's options (see PdfTools for option types).
 * Options hidden by "when" are not required.
 */
class ToolOptionRules
{
    /** Kinds of placements the page editor can create. */
    public const KINDS = ['text', 'rect', 'highlight', 'image', 'note', 'signature', 'redact', 'field-text', 'field-checkbox'];

    private const HEX = 'regex:/^#[0-9A-Fa-f]{6}$/';

    public function __construct(
        private array $definition,
        private array $values,
        private int $pageCount,
        private array $assets,
        private array $fields,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->definition['options'] as $name => $option) {
            $key = "options.{$name}";
            $visible = $this->visible($option);
            $required = $visible && ($option['required'] ?? false) ? 'required' : 'nullable';

            $rules += match ($option['type']) {
                'choice', 'select' => [$key => ['required', Rule::in(array_map('strval', array_keys($option['choices'])))]],
                'toggle' => [$key => ['boolean']],
                'text', 'password' => [$key => [$required, 'string', 'max:'.($option['max'] ?? 200)]],
                'textarea' => [$key => [$required, 'string', 'max:'.($option['max'] ?? 2000)]],
                'number', 'range' => [$key => ['required', 'numeric', 'min:'.($option['min'] ?? 0), 'max:'.($option['max'] ?? 10000)]],
                'color' => [$key => ['required', self::HEX]],
                'image' => [$key => [$visible && ($option['required'] ?? false) ? 'required' : 'nullable', Rule::in(array_keys($this->assets))]],
                'pages' => $this->pageRules($key, $option, $visible),
                'placements' => $this->placementRules($key, $option, $visible),
                'form' => $this->formRules($key),
            };
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'options.*.*.page.max' => 'That page doesn’t exist in this PDF.',
            'options.*.min' => 'Choose at least one page.',
            'options.*.in' => 'That image is no longer available. Add it again.',
            'options.*.regex' => 'Pick a valid color.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect($this->definition['options'])
            ->mapWithKeys(fn ($option, $name) => ["options.{$name}" => Str::lower($option['label'] ?? $name)])
            ->all();
    }

    private function visible(array $option): bool
    {
        foreach ($option['when'] ?? [] as $other => $allowed) {
            if (! in_array($this->values[$other] ?? null, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    private function pageRules(string $key, array $option, bool $visible): array
    {
        $page = ['required', 'integer', 'min:1', 'max:'.max(1, $this->pageCount)];

        return match ($option['mode']) {
            // Selected page numbers.
            'select' => [
                $key => [$visible && ($option['required'] ?? true) ? 'required' : 'nullable', 'array', 'min:'.($visible ? 1 : 0), 'max:'.max(1, $this->pageCount)],
                "{$key}.*" => $page,
            ],
            // The new document, as a list of source pages with rotation.
            'organize' => [
                $key => [$visible ? 'required' : 'nullable', 'array', 'min:'.($visible ? 1 : 0), 'max:'.max(1, $this->pageCount * 4)],
                "{$key}.*.page" => $page,
                "{$key}.*.rotation" => ['required', Rule::in([0, 90, 180, 270])],
            ],
            // Page number => clockwise rotation.
            'rotate' => [
                $key => ['nullable', 'array', 'max:'.max(1, $this->pageCount)],
                "{$key}.*" => ['required', Rule::in([0, 90, 180, 270])],
            ],
        };
    }

    private function placementRules(string $key, array $option, bool $visible): array
    {
        $fraction = ['required', 'numeric', 'min:0', 'max:1'];

        return [
            $key => [$visible && ($option['required'] ?? false) ? 'required' : 'nullable', 'array', 'max:500'],
            "{$key}.*.kind" => ['required', Rule::in(array_intersect(self::KINDS, $option['kinds']))],
            "{$key}.*.page" => ['required', 'integer', 'min:1', 'max:'.max(1, $this->pageCount)],
            "{$key}.*.x" => $fraction,
            "{$key}.*.y" => $fraction,
            "{$key}.*.w" => $fraction,
            "{$key}.*.h" => $fraction,
            "{$key}.*.text" => ['nullable', 'string', 'max:2000'],
            "{$key}.*.size" => ['nullable', 'numeric', 'min:4', 'max:200'],
            "{$key}.*.color" => ['nullable', self::HEX],
            "{$key}.*.asset" => ['nullable', Rule::in(array_keys($this->assets))],
            "{$key}.*.name" => ['nullable', 'string', 'max:100', 'regex:/^[\w\- .]+$/u'],
        ];
    }

    private function formRules(string $key): array
    {
        $rules = [$key => ['nullable', 'array']];

        foreach ($this->fields as $i => $field) {
            $rules["{$key}.{$i}"] = $field['type'] === 'checkbox'
                ? ['nullable', 'boolean']
                : ['nullable', 'string', 'max:5000'];
        }

        return $rules;
    }
}
