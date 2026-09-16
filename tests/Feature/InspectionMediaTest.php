<?php

use App\Models\Inspection;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake('local');
});

it('stores and serves image photos on inspection items', function () {
    $owner = User::factory()->owner()->create();
    $inspection = Inspection::factory()->create();
    $item = $inspection->items()->create(['label' => 'Kitchen', 'position' => 0]);

    $this->actingAs($owner)
        ->post(route('inspections.items.photos.store', [$inspection, $item]), [
            'file' => UploadedFile::fake()->create('kitchen.jpg', 10, 'image/jpeg'),
        ])
        ->assertRedirect();

    $media = $item->fresh()->media()->firstOrFail();

    $this->actingAs($owner)
        ->get(route('inspections.items.photos.show', [$inspection, $item, $media]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

it('rejects non-image inspection attachments', function () {
    $owner = User::factory()->owner()->create();
    $inspection = Inspection::factory()->create();
    $item = $inspection->items()->create(['label' => 'Kitchen', 'position' => 0]);

    $this->actingAs($owner)
        ->post(route('inspections.items.photos.store', [$inspection, $item]), [
            'file' => UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('file');
});

it('keeps completed photos readable and rejects photo mutations', function () {
    $owner = User::factory()->owner()->create();
    $inspection = Inspection::factory()->create();
    $item = $inspection->items()->create(['label' => 'Kitchen', 'position' => 0]);

    $this->actingAs($owner)->post(route('inspections.items.photos.store', [$inspection, $item]), [
        'file' => UploadedFile::fake()->create('kitchen.png', 10, 'image/png'),
    ]);
    $media = $item->fresh()->media()->firstOrFail();
    $item->update(['condition' => 'good']);
    $inspection->update([
        'status' => 'completed',
        'completed_by' => $owner->id,
        'completed_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('inspections.items.photos.show', [$inspection, $item, $media]))
        ->assertOk();

    $this->actingAs($owner)
        ->post(route('inspections.items.photos.store', [$inspection, $item]), [
            'file' => UploadedFile::fake()->create('another.png', 10, 'image/png'),
        ])
        ->assertStatus(422);

    $this->actingAs($owner)
        ->delete(route('inspections.items.photos.destroy', [$inspection, $item, $media]))
        ->assertStatus(422);
});
