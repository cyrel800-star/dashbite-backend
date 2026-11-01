<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    // ========================================
    // PDF W3.01 AC #2 — Disabled restaurant not orderable
    // ========================================
    public function test_catalog_excludes_disabled_restaurants(): void
    {
        $active    = Restaurant::factory()->create(['is_open' => true,  'is_disabled' => false]);
        $closed    = Restaurant::factory()->create(['is_open' => false, 'is_disabled' => false]);
        $disabled  = Restaurant::factory()->create(['is_open' => true,  'is_disabled' => true]);

        $response = $this->getJson('/api/catalog/restaurants');

        $response->assertOk()
                 ->assertJsonPath('meta.count', 1)
                 ->assertJsonFragment(['id' => $active->id])
                 ->assertJsonMissing(['id' => $closed->id])
                 ->assertJsonMissing(['id' => $disabled->id]);
    }

    /** PDF W3.01 AC #2 */
    public function test_disabled_restaurant_not_orderable(): void
    {
        $disabled = Restaurant::factory()->create(['is_open' => true, 'is_disabled' => true]);

        $this->getJson("/api/catalog/restaurants/{$disabled->id}")
            ->assertStatus(404)
            ->assertJson(['error' => 'restaurant_not_orderable']);

        $this->getJson("/api/catalog/restaurants/{$disabled->id}/menu")
            ->assertStatus(404)
            ->assertJson(['error' => 'restaurant_not_orderable']);
    }

    /** PDF W3.01 AC #2 — closed restaurant din */
    public function test_closed_restaurant_not_orderable(): void
    {
        $closed = Restaurant::factory()->create(['is_open' => false, 'is_disabled' => false]);

        $this->getJson("/api/catalog/restaurants/{$closed->id}")
            ->assertStatus(404)
            ->assertJson(['error' => 'restaurant_not_orderable']);
    }

    // ========================================
    // PDF W3.01 AC #1 — Out-of-stock items excluded from customer menu
    // (Final acceptance sa W3.04, pero dito nag-start ang behavior)
    // ========================================
    public function test_catalog_menu_excludes_unavailable_items(): void
    {
        $restaurant = Restaurant::factory()->create(['is_open' => true, 'is_disabled' => false]);
        $available   = MenuItem::factory()->create(['restaurant_id' => $restaurant->id, 'is_available' => true]);
        $unavailable = MenuItem::factory()->create(['restaurant_id' => $restaurant->id, 'is_available' => false]);

        $response = $this->getJson("/api/catalog/restaurants/{$restaurant->id}/menu");

        $response->assertOk()
                 ->assertJsonPath('meta.count', 1)
                 ->assertJsonFragment(['id' => $available->id])
                 ->assertJsonMissing(['id' => $unavailable->id]);
    }

    // ========================================
    // Restaurant portal — menu CRUD
    // ========================================
    public function test_restaurant_can_create_menu_item(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create([
            'role'           => UserRole::Restaurant,
            'account_status' => AccountStatus::Approved,
            'restaurant_id'  => $restaurant->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/restaurant/menu/items', [
            'name'  => 'New Adobo',
            'price' => 99.50,
        ])->assertStatus(201)
          ->assertJsonPath('data.name', 'New Adobo')
          ->assertJsonPath('data.is_available', true);

        $this->assertDatabaseHas('menu_items', [
            'restaurant_id' => $restaurant->id,
            'name'          => 'New Adobo',
        ]);
    }

    public function test_restaurant_can_toggle_item_availability(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create([
            'role'           => UserRole::Restaurant,
            'account_status' => AccountStatus::Approved,
            'restaurant_id'  => $restaurant->id,
        ]);
        $item = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_available'  => true,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/restaurant/menu/items/{$item->id}/availability")
            ->assertOk()
            ->assertJsonPath('data.is_available', false);

        $this->assertDatabaseHas('menu_items', [
            'id'           => $item->id,
            'is_available' => false,
        ]);
    }

    public function test_restaurant_cannot_edit_other_restaurant_menu(): void
    {
        $own     = Restaurant::factory()->create();
        $other   = Restaurant::factory()->create();
        $user = User::factory()->create([
            'role'           => UserRole::Restaurant,
            'account_status' => AccountStatus::Approved,
            'restaurant_id'  => $own->id,
        ]);
        $otherItem = MenuItem::factory()->create(['restaurant_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->putJson("/api/restaurant/menu/items/{$otherItem->id}", ['name' => 'Hacked'])
            ->assertStatus(404);

        $this->assertDatabaseMissing('menu_items', [
            'id'   => $otherItem->id,
            'name' => 'Hacked',
        ]);
    }

    public function test_unapproved_restaurant_cannot_manage_menu(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create([
            'role'           => UserRole::Restaurant,
            'account_status' => AccountStatus::Pending,
            'restaurant_id'  => $restaurant->id,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/restaurant/menu')
            ->assertStatus(403)
            ->assertJson(['error' => 'account_pending']);
    }

    public function test_customer_cannot_access_restaurant_menu_management(): void
    {
        $user = User::factory()->create([
            'role'           => UserRole::Customer,
            'account_status' => AccountStatus::Approved,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/restaurant/menu')
            ->assertStatus(403)
            ->assertJson(['error' => 'forbidden_role']);
    }
}