<?php
namespace App\Services;

use App\Domain\Errors;
use App\Domain\InvalidInputException;

class InputValidator
{
    /**
     * @param array $data
     * @return array valid points
     * @throws InvalidInputException
     */
    public function validate(array $data): array
    {
        $valid = [];
        foreach ($data['points'] as $index => $pt) {
            $lat = $pt['lat'] ?? null;
            $lon = $pt['lon'] ?? null;
            $hasRef = array_key_exists('ref', $pt);
            $ref = $pt['ref'] ?? null;
            if (!is_numeric($lat) || !is_numeric($lon)) {
                throw new InvalidInputException('Invalid coordinate', [
                    'index' => $index,
                    'ref' => $ref,
                    'lat' => $lat,
                    'lon' => $lon
                ]);
            }
            if ($hasRef && $ref !== null && !preg_match('/^[A-Za-z0-9._:-]{0,128}$/', $ref)) {
                throw new InvalidInputException('Invalid ref', [
                    'index' => $index,
                    'ref' => $ref
                ]);
            }
            $point = [
                'index' => $index,
                'lat' => (float)$lat,
                'lon' => (float)$lon,
            ];
            if ($hasRef) {
                $point['ref'] = $ref;
            }
            $valid[] = $point;
        }
        return $valid;
    }

    /**
     * Validate positions for summarize_stays.
     *
     * @param array $data
     * @return array valid positions
     * @throws InvalidInputException
     */
    public function validatePositions(array $data): array
    {
        $valid = [];
        $prevTs = null;

        foreach ($data['positions'] as $index => $pos) {
            $ts = $pos['timestamp'] ?? null;
            $lat = $pos['lat'] ?? null;
            $lon = $pos['lon'] ?? null;

            if (!is_numeric($ts)) {
                throw new InvalidInputException('Invalid timestamp', ['index' => $index]);
            }
            if ($prevTs !== null && $ts < $prevTs) {
                throw new InvalidInputException('Invalid timestamp', ['index' => $index]);
            }
            if (!is_numeric($lat) || !is_numeric($lon)) {
                throw new InvalidInputException('Invalid coordinate', ['index' => $index]);
            }

            $valid[] = [
                'timestamp' => (int)$ts,
                'lat' => (float)$lat,
                'lon' => (float)$lon,
            ];
            $prevTs = (int)$ts;
        }

        return $valid;
    }
}
