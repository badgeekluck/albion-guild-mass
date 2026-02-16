<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SavedBuild;
use App\Models\GameRole;

class BuildController extends Controller
{
    public function index()
    {
        $builds = SavedBuild::orderBy('created_at', 'desc')->get();
        return view('admin.build.index', compact('builds'));
    }

    public function create()
    {
        // 1. SİLAHLAR (Kategorilerine göre)
        // Dropdown'da gruplamak için ayrı ayrı çekmiyoruz, hepsini alıp Blade'de gruplayacağız.
        $weapons = GameRole::whereIn('category', ['DPS', 'Tank', 'Healer', 'Support'])
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // 2. KAFALIKLAR (Plate, Leather, Cloth)
        $helmets = GameRole::whereIn('category', [
            'Plate Armor Helmet',
            'Leather Armor Helmet',
            'Cloth Armor Helmet'
        ])
            ->orderBy('category') // Önce kategoriye göre sırala (Gruplama için)
            ->orderBy('name')
            ->get();

        // 3. ZIRHLAR / GÖĞÜSLÜKLER
        $armors = GameRole::whereIn('category', [
            'Plate Armor Chest',
            'Leather Armor Chest',
            'Cloth Armor Chest'
        ])
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // 4. AYAKKABILAR
        $shoes = GameRole::whereIn('category', [
            'Plate Armor Shoes',
            'Leather Armor Shoes',
            'Cloth Armor Shoes'
        ])
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // 5. YAN EŞYALAR (Offhand & Shield)
        $offhands = GameRole::whereIn('category', ['Offhand', 'Shield'])
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // 6. PELERİNLER
        $capes = GameRole::where('category', 'Cape')
            ->orderBy('name')
            ->get();

        $foods = GameRole::where('category', 'Food')->orderBy('name')->get();
        $potions = GameRole::where('category', 'Potion')->orderBy('name')->get();

        return view('admin.build.create', compact('weapons', 'helmets', 'armors', 'shoes', 'offhands', 'capes', 'foods', 'potions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'role_category' => 'required',
            'weapon_id' => 'required|exists:game_roles,id',
        ]);

        SavedBuild::create($request->all());

        return redirect()->route('builds.index')->with('success', 'Build başarıyla kaydedildi! 🛡️');
    }

    public function destroy($id)
    {
        SavedBuild::findOrFail($id)->delete();
        return back()->with('success', 'Build silindi.');
    }

    public function edit($id)
    {
        $build = SavedBuild::findOrFail($id);

        $weapons = GameRole::whereIn('category', ['DPS', 'Tank', 'Healer', 'Support'])->orderBy('category')->orderBy('name')->get();
        $helmets = GameRole::whereIn('category', ['Plate Armor Helmet', 'Leather Armor Helmet', 'Cloth Armor Helmet'])->orderBy('category')->orderBy('name')->get();
        $armors = GameRole::whereIn('category', ['Plate Armor Chest', 'Leather Armor Chest', 'Cloth Armor Chest'])->orderBy('category')->orderBy('name')->get();
        $shoes = GameRole::whereIn('category', ['Plate Armor Shoes', 'Leather Armor Shoes', 'Cloth Armor Shoes'])->orderBy('category')->orderBy('name')->get();
        $offhands = GameRole::whereIn('category', ['Offhand', 'Shield'])->orderBy('category')->orderBy('name')->get();
        $capes = GameRole::where('category', 'Cape')->orderBy('name')->get();
        $foods = GameRole::where('category', 'Food')->orderBy('name')->get();
        $potions = GameRole::where('category', 'Potion')->orderBy('name')->get();

        return view('admin.build.edit', compact('build', 'weapons', 'helmets', 'armors', 'shoes', 'offhands', 'capes', 'foods', 'potions'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'role_category' => 'required',
            'weapon_id' => 'required|exists:game_roles,id',
        ]);

        $build = SavedBuild::findOrFail($id);
        $build->update($request->all());

        return redirect()->route('builds.index')->with('success', 'Build başarıyla güncellendi! ✨');
    }
}
