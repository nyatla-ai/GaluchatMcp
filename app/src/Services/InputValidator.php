<?php
namespace App\Services;

use App\Domain\Errors;

class InputValidator
{
    /**
     * @param array $data
     * @return array [validPoints, errorPoints]
     */
    public function validate(array $data): array
    {
        $valid = [];
        $errors = [];
        foreach ($data['points'] as $index => $pt) {
            $lat = $pt['lat'] ?? null;
            $lon = $pt['lon'] ?? null;
            $ref = $pt['ref'] ?? null;
            // coord check
            if (!is_numeric($lat) || !is_numeric($lon)) {
                $errors[] = [
                    'index' => $index,
                    'lat' => $lat,
                    'lon' => $lon,
                    'ref' => $ref,
                    'reason' => Errors::INVALID_COORD
                ];
                continue;
            }
            // ref validation
            if ($ref !== null && !preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $ref)) {
                $errors[] = [
                    'index' => $index,
                    'lat' => $lat,
                    'lon' => $lon,
                    'ref' => $ref,
                    'reason' => Errors::INVALID_REF
                ];
                continue;
            }
            $valid[] = [
                'index' => $index,
                'lat' => (float)$lat,
                'lon' => (float)$lon,
                'ref' => $ref
            ];
        }
        return [$valid, $errors];
    }
}
