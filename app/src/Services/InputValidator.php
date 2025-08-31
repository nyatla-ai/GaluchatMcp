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
     * @param array $data
     * @return array [validSamples, errorSamples]
     */
    public function validateSamples(array $data): array
    {
        $valid = [];
        $errors = [];
        foreach ($data['samples'] as $sample) {
            $ref = $sample['ref'] ?? null;
            $area = $sample['area'] ?? null;
            $start = $sample['start'] ?? null;
            $end = $sample['end'] ?? null;

            if ($ref !== null && !preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $ref)) {
                $errors[] = [
                    'ref' => $ref,
                    'reason' => Errors::INVALID_REF
                ];
                continue;
            }

            if (!is_string($area) || $area === '') {
                $errors[] = [
                    'ref' => $ref,
                    'reason' => Errors::INVALID_AREA
                ];
                continue;
            }

            $startTs = strtotime($start);
            $endTs = strtotime($end);
            if ($startTs === false || $endTs === false || $startTs >= $endTs) {
                $errors[] = [
                    'ref' => $ref,
                    'reason' => Errors::INVALID_TIME_RANGE
                ];
                continue;
            }

            $valid[] = [
                'ref' => $ref,
                'area' => $area,
                'start' => (new \DateTime($start))->format(DATE_RFC3339),
                'end' => (new \DateTime($end))->format(DATE_RFC3339)
            ];
        }

        return [$valid, $errors];
    }
}
