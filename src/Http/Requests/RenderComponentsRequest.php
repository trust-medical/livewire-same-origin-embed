<?php

declare(strict_types=1);

namespace TrustMedical\SameOriginLivewireBridge\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use TrustMedical\SameOriginLivewireBridge\Support\JsonDuplicateKeyDetector;
use TrustMedical\SameOriginLivewireBridge\Support\ParamShape;

final class RenderComponentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxComponents = (int) config('livewire-bridge.max_components_per_request', 10);
        $aliasPattern = (string) config('livewire-bridge.component_alias_pattern');
        $keyPattern = (string) config('livewire-bridge.component_key_pattern');

        return [
            'components' => ['required', 'array', 'min:1', 'max:'.$maxComponents],
            'components.*.component' => ['required', 'string', 'regex:'.$aliasPattern],
            'components.*.key' => ['required', 'string', 'regex:'.$keyPattern],
            'components.*.params' => ['sometimes', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $maxBodyBytes = (int) config('livewire-bridge.max_body_bytes', 65536);

        if (strlen($this->getContent()) > $maxBodyBytes) {
            $this->reject('body_too_large', 'The request body is too large.');
        }

        if (JsonDuplicateKeyDetector::containsDuplicateKeys($this->getContent())) {
            $this->reject('duplicate_json_key', 'The request body contains duplicate object keys.');
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $components = $this->input('components', []);

            if (! is_array($components)) {
                return;
            }

            $keys = [];

            foreach ($components as $index => $component) {
                if (! is_array($component)) {
                    continue;
                }

                $key = $component['key'] ?? null;

                if (is_string($key)) {
                    if (isset($keys[$key])) {
                        $validator->errors()->add("components.$index.key", 'Component keys must be unique.');
                    }

                    $keys[$key] = true;
                }

                $params = $component['params'] ?? [];

                if (! is_array($params) || ! ParamShape::isAssociativeArray($params)) {
                    $validator->errors()->add("components.$index.params", 'Params must be an object.');

                    continue;
                }

                if (! ParamShape::withinDepth($params, (int) config('livewire-bridge.max_params_depth', 3))) {
                    $validator->errors()->add("components.$index.params", 'Params exceed the maximum depth.');
                }

                if (! ParamShape::withinStringLength($params, (int) config('livewire-bridge.max_param_string_length', 1000))) {
                    $validator->errors()->add("components.$index.params", 'Params contain a string that is too long.');
                }
            }
        });
    }

    /**
     * @return list<array{component: string, key: string, params: array<string, mixed>}>
     */
    public function components(): array
    {
        /** @var list<array{component: string, key: string, params?: array<string, mixed>}> $components */
        $components = $this->validated('components');

        return array_map(
            fn (array $component): array => [
                'component' => $component['component'],
                'key' => $component['key'],
                'params' => $component['params'] ?? [],
            ],
            $components
        );
    }

    /**
     * @return list<string>
     */
    public function componentAliases(): array
    {
        return array_values(array_unique(array_map(
            fn (array $component): string => (string) ($component['component'] ?? ''),
            is_array($this->input('components')) ? $this->input('components') : []
        )));
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'request_validation_failed',
                'message' => 'The render request is invalid.',
            ],
            'errors' => $validator->errors(),
        ], 422));
    }

    private function reject(string $code, string $message): never
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], 422));
    }
}
