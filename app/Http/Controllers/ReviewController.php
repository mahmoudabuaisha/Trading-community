<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewRequest;
use App\Http\Resources\DailyReviewResource;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    /** Upsert the caller's review for one calendar day. */
    public function update(ReviewRequest $request, string $date): JsonResponse
    {
        $user = $request->user();
        $review = $user->dailyReviews()->whereDate('date', $date)->first()
            ?? $user->dailyReviews()->make(['date' => $date]);

        $review->fill(array_map(fn ($text) => $text ?? '', $request->safe()->except('date')));
        $review->updated_at = now(); // "saved" even when the text is unchanged
        $review->save();

        return response()->json(['review' => new DailyReviewResource($review)]);
    }
}
