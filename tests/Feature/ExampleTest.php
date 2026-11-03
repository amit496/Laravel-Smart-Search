<?php

namespace Tests\Feature;

use App\Models\SearchRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_search_page_and_autocomplete_api_return_suggestions(): void
    {
        DB::table('search_api_usage_counters')->where('id', 1)->update([
            'usage_count' => 0,
        ]);
        Cache::store('file')->forget('search:'.md5('lon').':1');
        SearchRecord::query()->create([
            'content' => 'London Road',
        ]);

        $this->get('/search')->assertOk();
        $this->getJson('/api/search?search=Lon')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.content', 'London Road')
            ->assertJsonStructure(['data' => [['selection_url']]]);
    }

    public function test_searching_does_not_increment_the_selection_usage_counter(): void
    {
        Cache::store('file')->forget('search:'.md5('lon').':1');
        SearchRecord::query()->create([
            'content' => 'London Road',
        ]);
        DB::table('search_api_usage_counters')->where('id', 1)->update([
            'usage_count' => 0,
        ]);

        $this->getJson('/api/search?search=Lon')->assertOk();

        $this->assertDatabaseHas('search_api_usage_counters', [
            'id' => 1,
            'usage_count' => 0,
        ]);
    }

    public function test_selecting_a_signed_suggestion_increments_the_counter_once(): void
    {
        $record = SearchRecord::query()->create([
            'content' => 'London Road',
        ]);
        DB::table('search_api_usage_counters')->where('id', 1)->update([
            'usage_count' => 0,
        ]);
        $key = (string) Str::uuid();
        $selectionUrl = URL::temporarySignedRoute(
            'api.search.select',
            now()->addMinutes(10),
            ['search_record' => $record->id, 'key' => $key],
            false
        );

        $this->postJson($selectionUrl)
            ->assertOk()
            ->assertJson([
                'success' => true,
                'recorded' => true,
                'usage_count' => 1,
            ]);

        $this->postJson($selectionUrl)
            ->assertOk()
            ->assertJson([
                'success' => true,
                'recorded' => false,
                'usage_count' => 1,
            ]);
    }

    public function test_selecting_a_second_suggestion_increments_the_counter_again(): void
    {
        DB::table('search_api_usage_counters')->where('id', 1)->update([
            'usage_count' => 0,
        ]);
        $firstRecord = SearchRecord::query()->create([
            'content' => 'London Road',
        ]);
        $secondRecord = SearchRecord::query()->create([
            'content' => 'London Street',
        ]);
        $firstUrl = URL::temporarySignedRoute(
            'api.search.select',
            now()->addMinutes(10),
            ['search_record' => $firstRecord->id, 'key' => (string) Str::uuid()],
            false
        );
        $secondUrl = URL::temporarySignedRoute(
            'api.search.select',
            now()->addMinutes(10),
            ['search_record' => $secondRecord->id, 'key' => (string) Str::uuid()],
            false
        );

        $this->postJson($firstUrl)->assertJsonPath('usage_count', 1);
        $this->postJson($secondUrl)->assertJsonPath('usage_count', 2);

        $this->assertDatabaseHas('search_api_usage_counters', [
            'id' => 1,
            'usage_count' => 2,
        ]);
    }

    public function test_an_unsigned_suggestion_selection_does_not_increment_the_counter(): void
    {
        DB::table('search_api_usage_counters')->where('id', 1)->update([
            'usage_count' => 0,
        ]);
        SearchRecord::query()->create([
            'content' => 'London Road',
        ]);

        $this->postJson('/api/search/1/select?key='.Str::uuid())
            ->assertForbidden();

        $this->assertDatabaseHas('search_api_usage_counters', [
            'id' => 1,
            'usage_count' => 0,
        ]);
    }
}
