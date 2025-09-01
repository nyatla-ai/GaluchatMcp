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

    /**
     * Validate positions for summarize_stays.
     *
     * @param array $data
     * @return array [validPositions, errorPositions]
     */
    public function validatePositions(array $data): array
    {
        $valid = [];
        $errors = [];
        $prevTs = null;

        foreach ($data['positions'] as $index => $pos) {
            $ts = $pos['timestamp'] ?? null;
            $lat = $pos['lat'] ?? null;
            $lon = $pos['lon'] ?? null;

            if (!is_numeric($ts)) {
                $errors[] = ['index' => $index, 'reason' => Errors::INVALID_TIMESTAMP];
                continue;
            }
            if ($prevTs !== null && $ts < $prevTs) {
                $errors[] = ['index' => $index, 'reason' => Errors::INVALID_TIMESTAMP];
                continue;
            }
            if (!is_numeric($lat) || !is_numeric($lon)) {
                $errors[] = ['index' => $index, 'reason' => Errors::INVALID_COORD];
                continue;
            }

            $valid[] = [
                'timestamp' => (int)$ts,
                'lat' => (float)$lat,
                'lon' => (float)$lon,
            ];
            $prevTs = (int)$ts;
        }

        return [$valid, $errors];
    }
}
