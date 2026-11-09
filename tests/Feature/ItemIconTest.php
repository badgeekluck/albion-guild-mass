<?php

namespace Tests\Feature;

use App\Http\Controllers\ItemIconController;
use App\Models\GameRole;
use App\Models\SavedBuild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ItemIconTest extends TestCase
{
    use RefreshDatabase;

    private const ID = 'T8_TEST_ICON_FIXTURE';

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = public_path(ItemIconController::DIRECTORY.'/'.self::ID.'.png');
        File::delete([$this->path, $this->path.'.missing']);
    }

    protected function tearDown(): void
    {
        File::delete([$this->path, $this->path.'.missing']);
        parent::tearDown();
    }

    public function test_icon_is_downloaded_once_by_unique_id_and_then_served_from_disk(): void
    {
        Http::fake(['render.albiononline.com/*' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png'])]);

        $this->get('/item-icons/'.self::ID.'.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/item-icons/'.self::ID.'.png')->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://render.albiononline.com/v1/item/'.self::ID.'.png'));
        $this->assertSame('PNGDATA', File::get($this->path));
    }

    public function test_failed_icons_are_not_retried_on_every_request(): void
    {
        Http::fake(['*' => Http::response('nope', 404)]);

        $this->get('/item-icons/'.self::ID.'.png')->assertNotFound();
        $this->get('/item-icons/'.self::ID.'.png')->assertNotFound();

        Http::assertSentCount(1);
        $this->assertFileExists($this->path.'.missing');
    }

    public function test_transient_errors_are_retried_on_the_next_view(): void
    {
        Http::fakeSequence()->pushStatus(502)->pushStatus(502)->pushStatus(502)->pushStatus(502)
            ->push('PNGDATA', 200, ['Content-Type' => 'image/png']);

        $this->get('/item-icons/'.self::ID.'@3.png')->assertNotFound();
        $this->assertFileDoesNotExist(public_path(ItemIconController::DIRECTORY.'/'.self::ID.'@3.png.missing'));

        $this->get('/item-icons/'.self::ID.'@3.png')->assertOk();
        File::delete(public_path(ItemIconController::DIRECTORY.'/'.self::ID.'@3.png'));
    }

    public function test_only_item_ids_are_accepted(): void
    {
        Http::fake();

        $this->get('/item-icons/evil.png')->assertNotFound();
        $this->get('/item-icons/T8_MACE%2F..png')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_build_modal_data_uses_local_icon_urls(): void
    {
        $mace = GameRole::create(['name' => '1h Mace', 'category' => 'Tank', 'unique_name' => 'T8_MAIN_MACE']);
        $mount = GameRole::create(['name' => 'Behemoth', 'category' => 'Battle Mount']);
        $build = new SavedBuild(['name' => 'B', 'role_category' => 'Tank', 'weapon_id' => $mace->id, 'mount_id' => $mount->id]);
        $build->save();

        $items = $build->toModalArray()['items'];

        $this->assertSame(['name' => '1h Mace', 'icon_url' => '/item-icons/T8_MAIN_MACE.png'], $items['weapon']);
        $this->assertSame(['name' => 'Behemoth', 'icon_url' => null], $items['mount']);
    }
}
