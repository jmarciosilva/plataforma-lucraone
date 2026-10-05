<?php

namespace App\Modules\Products\Http\Concerns;

use Illuminate\Support\Arr;

/** Preserva o literal decimal JSON antes de o decoder perder escala/notação. */
trait PreservesQuantityInput
{
    protected function prepareForValidation(): void
    {
        if (! $this->isJson()) {
            return;
        }

        // Strings JSON são consumidas inteiras: conteúdo textual não é alterado.
        // Apenas valores numéricos de chaves "quantity" viram strings decimais.
        $body = preg_replace_callback(
            '/(?<key>"(?:[^"\\\\]|\\\\.)*")(?<separator>\s*:\s*)(?<number>-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)(?=\s*[,}])|"(?:[^"\\\\]|\\\\.)*"/s',
            static function (array $match): string {
                if (isset($match['number']) && json_decode($match['key']) === 'quantity') {
                    return $match['key'].$match['separator'].json_encode($match['number']);
                }

                return $match[0];
            },
            $this->getContent()
        );
        $data = json_decode($body ?? '', true);
        $original = json_decode($this->getContent(), true);
        if (! is_array($data) || ! is_array($original)) {
            return;
        }

        // Conservar TrimStrings e as demais normalizações já aplicadas.
        $input = $this->getInputSource()->all();
        foreach (Arr::dot($data) as $path => $quantity) {
            if ($path !== 'quantity' && ! str_ends_with($path, '.quantity')) {
                continue;
            }
            $decoded = Arr::get($original, $path);
            if (is_int($decoded) || is_float($decoded)) {
                Arr::set($input, $path, $quantity);
            }
        }
        $this->replace($input);
    }
}
