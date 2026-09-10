<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAssessmentPhotoConsentRequest;
use App\Models\Assessment;
use App\Models\AssessmentPhotoConsent;
use Illuminate\Http\JsonResponse;

class AssessmentPhotoConsentController extends Controller
{
    public function update(
        UpdateAssessmentPhotoConsentRequest $request,
        Assessment $assessment
    ): JsonResponse {
        if ($assessment->isCompleted()) {
            abort(
                422,
                'Avaliações concluídas não podem ser alteradas.'
            );
        }

        $data =
            $request->validated();

        $consent =
            $assessment
                ->photoConsent()
                ->firstOrNew();

        if (! $consent->exists) {
            $consent->registered_by =
                $request
                    ->user()
                    ->id;
        }

        $consent->fill([
            'purpose' =>
                $data['purpose'],

            'consent_date' =>
                $data['consent_date'],

            'external_use_allowed' =>
                $data[
                    'external_use_allowed'
                ],

            'storage_authorized' =>
                $data[
                    'storage_authorized'
                ],

            'revoked_at' =>
                null,

            'revoked_by' =>
                null,

            'updated_by' =>
                $request
                    ->user()
                    ->id,
        ]);

        $consent->save();

        return response()->json([
            'data' =>
                $this->serialize(
                    $consent->fresh()
                ),
        ]);
    }

    public function destroy(
        Assessment $assessment
    ): JsonResponse {
        $consent =
            $assessment
                ->photoConsent()
                ->first();

        if (! $consent) {
            abort(
                404,
                'Consentimento não encontrado.'
            );
        }

        if (
            $consent->revoked_at ===
            null
        ) {
            $consent->update([
                'revoked_at' =>
                    now(),

                'revoked_by' =>
                    request()
                        ->user()
                        ->id,

                'updated_by' =>
                    request()
                        ->user()
                        ->id,
            ]);
        }

        return response()->json([
            'data' =>
                $this->serialize(
                    $consent->fresh()
                ),
        ]);
    }

    private function serialize(
        AssessmentPhotoConsent $consent
    ): array {
        return [
            'purpose' =>
                $consent->purpose,

            'consent_date' =>
                $consent
                    ->consent_date
                    ->format('Y-m-d'),

            'external_use_allowed' =>
                $consent
                    ->external_use_allowed,

            'storage_authorized' =>
                $consent
                    ->storage_authorized,

            'revoked_at' =>
                $consent
                    ->revoked_at
                    ?->toIso8601String(),

            'active' =>
                $consent
                    ->isActive(),
        ];
    }
}
