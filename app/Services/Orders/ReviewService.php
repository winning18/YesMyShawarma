<?php

namespace App\Services\Orders;

use App\Exceptions\ReviewException;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A review is customer feedback tied to a completed order — public-facing
 * once a moderator approves it (see permissions.md's reviews.moderate),
 * invisible before that. Deliberately never touches orders.status or
 * OrderStateMachine, same reasoning as RefundService: this is feedback
 * about an order, not a change to its operational workflow.
 */
class ReviewService
{
    /**
     * Pickup orders never reach "delivered" — collection is their terminal
     * transition (orders.md: "ready → dispatched on collection", and
     * there's no further step after that for pickup — see
     * OrderStateMachine::TRANSITIONS and tracking/show.blade.php's
     * buildSteps(), which omits a "delivered" step for pickup entirely).
     * So eligibility means "reached its own terminal successful status",
     * not literally orders.status === 'delivered'.
     */
    public function isEligible(Order $order): bool
    {
        return $order->status === 'delivered'
            || ($order->fulfilment_type === 'pickup' && $order->status === 'dispatched');
    }

    public function submit(Order $order, int $rating, ?string $comment): Review
    {
        return DB::transaction(function () use ($order, $rating, $comment) {
            if (! $this->isEligible($order)) {
                throw ReviewException::notYetEligible();
            }

            if ($rating < 1 || $rating > 5) {
                throw ReviewException::invalidRating();
            }

            if ($order->review()->exists()) {
                throw ReviewException::alreadyReviewed();
            }

            $review = Review::create([
                'order_id' => $order->id,
                'branch_id' => $order->branch_id,
                'customer_id' => $order->customer_id,
                'rating' => $rating,
                'comment' => $comment,
                'status' => Review::STATUS_PENDING,
            ]);

            $this->logEvent($order, 'customer', $order->customer_id, [
                'action' => 'review_submitted', 'review_id' => $review->id, 'rating' => $rating,
            ]);

            return $review;
        });
    }

    public function approve(Review $review, User $moderator, string $actorType): Review
    {
        $this->assertStatus($review, Review::STATUS_PENDING);

        $review->update([
            'status' => Review::STATUS_APPROVED,
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
        ]);

        $this->logEvent($review->order, $actorType, $moderator->id, [
            'action' => 'review_approved', 'review_id' => $review->id,
        ]);

        return $review->fresh();
    }

    public function reject(Review $review, User $moderator, string $actorType, ?string $note): Review
    {
        $this->assertStatus($review, Review::STATUS_PENDING);

        $review->update([
            'status' => Review::STATUS_REJECTED,
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
            'moderation_note' => $note,
        ]);

        $this->logEvent($review->order, $actorType, $moderator->id, [
            'action' => 'review_rejected', 'review_id' => $review->id,
        ]);

        return $review->fresh();
    }

    private function assertStatus(Review $review, string $expected): void
    {
        if ($review->status !== $expected) {
            throw ReviewException::wrongStatus($expected, $review->status);
        }
    }

    /**
     * from_status/to_status stay equal — a review is feedback, never a
     * status change (see class docblock) — same pattern as RefundService.
     *
     * @param  array<string, mixed>  $meta
     */
    private function logEvent(Order $order, string $actorType, ?int $actorId, array $meta): void
    {
        $order->events()->create([
            'from_status' => $order->status,
            'to_status' => $order->status,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'shift_id' => null,
            'meta' => $meta,
        ]);
    }
}
