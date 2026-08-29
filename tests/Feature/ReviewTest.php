<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    private function makeOwner(): User
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        return $owner;
    }

    private function makeManager(?Branch $branch = null): User
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $branch ?? $this->branch);

        return $manager;
    }

    private function makeGeneralManager(?Branch $branch = null): User
    {
        $gm = User::factory()->create();
        $this->assignRoleAt($gm, 'general_manager', $branch ?? $this->branch);

        return $gm;
    }

    private function makeOrder(string $status = 'delivered', string $fulfilmentType = 'pickup', ?Branch $branch = null): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => ($branch ?? $this->branch)->id,
            'fulfilment_type' => $fulfilmentType,
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'channel' => 'web',
        ]);
        $order->status = $status;
        $order->placed_at = now();
        $order->save();

        return $order;
    }

    public function test_customer_can_submit_a_review_on_a_delivered_order(): void
    {
        $order = $this->makeOrder('delivered', 'delivery');

        $response = $this->postJson(route('tracking.review.store', $order), [
            'rating' => 5, 'comment' => 'Great food!',
        ]);

        $response->assertOk();
        $response->assertJsonPath('review.rating', 5);
        $response->assertJsonPath('review.comment', 'Great food!');

        $this->assertDatabaseHas('reviews', [
            'order_id' => $order->id, 'branch_id' => $this->branch->id,
            'rating' => 5, 'status' => 'pending',
        ]);
    }

    public function test_pickup_order_is_reviewable_once_dispatched_without_ever_reaching_delivered(): void
    {
        $order = $this->makeOrder('dispatched', 'pickup');

        $this->postJson(route('tracking.review.store', $order), ['rating' => 4])
            ->assertOk();

        $this->assertDatabaseHas('reviews', ['order_id' => $order->id, 'rating' => 4]);
    }

    public function test_cannot_review_an_order_that_has_not_reached_its_terminal_status(): void
    {
        $order = $this->makeOrder('preparing', 'pickup');

        $this->postJson(route('tracking.review.store', $order), ['rating' => 5])
            ->assertStatus(422);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_delivery_order_is_not_reviewable_merely_at_dispatched(): void
    {
        // Unlike pickup, "dispatched" for a delivery order means the rider
        // has it, not that the customer has received it yet.
        $order = $this->makeOrder('dispatched', 'delivery');

        $this->postJson(route('tracking.review.store', $order), ['rating' => 5])
            ->assertStatus(422);
    }

    public function test_cannot_submit_a_second_review_for_the_same_order(): void
    {
        $order = $this->makeOrder('delivered', 'delivery');

        $this->postJson(route('tracking.review.store', $order), ['rating' => 5])->assertOk();
        $this->postJson(route('tracking.review.store', $order), ['rating' => 3])->assertStatus(422);

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_rating_must_be_between_1_and_5(): void
    {
        $order = $this->makeOrder('delivered', 'delivery');

        $this->postJson(route('tracking.review.store', $order), ['rating' => 0])
            ->assertJsonValidationErrors('rating');

        $this->postJson(route('tracking.review.store', $order), ['rating' => 6])
            ->assertJsonValidationErrors('rating');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_comment_is_optional(): void
    {
        $order = $this->makeOrder('delivered', 'delivery');

        $this->postJson(route('tracking.review.store', $order), ['rating' => 5])
            ->assertOk()
            ->assertJsonPath('review.comment', null);
    }

    public function test_pending_review_is_hidden_from_the_public_reviews_page(): void
    {
        $order = $this->makeOrder('delivered', 'delivery');
        Review::create([
            'order_id' => $order->id, 'branch_id' => $this->branch->id, 'customer_id' => $order->customer_id,
            'rating' => 5, 'status' => Review::STATUS_PENDING,
        ]);

        $this->get(route('reviews.index'))->assertOk()->assertSee('No reviews yet');
    }

    public function test_approved_review_appears_on_the_public_reviews_page(): void
    {
        $order = $this->makeOrder('delivered', 'delivery');
        Review::create([
            'order_id' => $order->id, 'branch_id' => $this->branch->id, 'customer_id' => $order->customer_id,
            'rating' => 5, 'comment' => 'Excellent!', 'status' => Review::STATUS_APPROVED, 'moderated_at' => now(),
        ]);

        $this->get(route('reviews.index'))->assertOk()->assertSee('Excellent!');
    }

    public function test_manager_can_approve_a_review_at_their_branch(): void
    {
        $manager = $this->makeManager();
        $order = $this->makeOrder('delivered', 'delivery');
        $review = Review::create([
            'order_id' => $order->id, 'branch_id' => $this->branch->id, 'customer_id' => $order->customer_id,
            'rating' => 4, 'status' => Review::STATUS_PENDING,
        ]);

        $this->actingAs($manager)->post(route('dashboard.reviews.approve', $review))->assertRedirect();

        $this->assertSame('approved', $review->fresh()->status);
        $this->assertSame($manager->id, $review->fresh()->moderated_by);
    }

    public function test_manager_can_reject_a_review_at_their_branch(): void
    {
        $manager = $this->makeManager();
        $order = $this->makeOrder('delivered', 'delivery');
        $review = Review::create([
            'order_id' => $order->id, 'branch_id' => $this->branch->id, 'customer_id' => $order->customer_id,
            'rating' => 1, 'status' => Review::STATUS_PENDING,
        ]);

        $this->actingAs($manager)->post(route('dashboard.reviews.reject', $review), ['note' => 'Spam'])
            ->assertRedirect();

        $this->assertSame('rejected', $review->fresh()->status);
    }

    public function test_manager_cannot_moderate_a_review_at_a_branch_they_dont_hold(): void
    {
        $otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $manager = $this->makeManager($otherBranch);
        $order = $this->makeOrder('delivered', 'delivery');
        $review = Review::create([
            'order_id' => $order->id, 'branch_id' => $this->branch->id, 'customer_id' => $order->customer_id,
            'rating' => 4, 'status' => Review::STATUS_PENDING,
        ]);

        $this->actingAs($manager)->post(route('dashboard.reviews.approve', $review))->assertForbidden();
    }

    public function test_general_manager_can_moderate_across_branches_they_hold_that_role_at(): void
    {
        $gm = $this->makeGeneralManager();
        $order = $this->makeOrder('delivered', 'delivery');
        $review = Review::create([
            'order_id' => $order->id, 'branch_id' => $this->branch->id, 'customer_id' => $order->customer_id,
            'rating' => 5, 'status' => Review::STATUS_PENDING,
        ]);

        $this->actingAs($gm)->post(route('dashboard.reviews.approve', $review))->assertRedirect();
        $this->assertSame('approved', $review->fresh()->status);
    }

    public function test_owner_can_moderate_reviews_at_any_branch(): void
    {
        $owner = $this->makeOwner();
        $order = $this->makeOrder('delivered', 'delivery');
        $review = Review::create([
            'order_id' => $order->id, 'branch_id' => $this->branch->id, 'customer_id' => $order->customer_id,
            'rating' => 5, 'status' => Review::STATUS_PENDING,
        ]);

        $this->actingAs($owner)->post(route('dashboard.reviews.approve', $review))->assertRedirect();
    }

    public function test_staff_cannot_access_review_moderation(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard.reviews.index'))->assertForbidden();
    }

    public function test_rider_cannot_access_review_moderation(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branch);

        $this->actingAs($rider)->get(route('dashboard.reviews.index'))->assertForbidden();
    }
}
