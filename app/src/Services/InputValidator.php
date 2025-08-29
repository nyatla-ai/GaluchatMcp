<?php
namespace App\Services;

use App\Domain\Errors;

class InputValidator
{
    /**
     * @param array $data Parsed request data
     * @return array [validPoints, failed]
     */
    public function validate(array $data): array
    {
        $valid = [];
        $failed = [];
        foreach ($data['points'] as $index => $pt) {
            $lat = round($pt['lat'], 6);
            $lon = round($pt['lon'], 6);
            $ref = $pt['ref'] ?? null;
            if ($lat < 20.0 || $lat > 50.0 || $lon < 120.0 || $lon > 155.0) {
                $entry = ['index' => $index, 'code' => Errors::OUT_OF_RANGE];
                if ($ref !== null) {
                    $entry['ref'] = $ref;
                }
                $failed[] = $entry;
                continue;
            }
            $valid[] = ['index' => $index, 'lat' => $lat, 'lon' => $lon, 'ref' => $ref];
        }
        return [$valid, $failed];
    }
}
