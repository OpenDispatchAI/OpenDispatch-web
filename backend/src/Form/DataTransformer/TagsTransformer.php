<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<array, string>
 */
class TagsTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): string
    {
        if (!is_array($value)) {
            return '';
        }
        return implode(', ', $value);
    }

    public function reverseTransform(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
