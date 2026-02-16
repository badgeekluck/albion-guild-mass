<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Build</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #111827; color: #e5e7eb; padding: 40px; }
        .container { max-width: 900px; margin: 0 auto; background: #1f2937; padding: 30px; border-radius: 12px; border: 1px solid #374151; }

        /* Form Düzeni */
        label { display: block; margin-bottom: 6px; font-weight: bold; color: #fbbf24; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        select, input, textarea { width: 100%; padding: 12px; background: #374151; border: 1px solid #4b5563; color: white; border-radius: 6px; margin-bottom: 5px; font-size: 14px; }
        select:focus, input:focus { outline:none; border-color: #6366f1; background: #2d3748; }

        /* Dropdown Grupları */
        optgroup { background: #111827; color: #9ca3af; font-style: normal; font-weight: bold; }
        option { background: #374151; color: white; padding: 5px; }

        /* Grid Sistemi */
        .grid-main { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .grid-equip { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; align-items: end; }

        /* Butonlar */
        button { background: #10b981; color: white; padding: 15px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; font-size: 16px; margin-top: 20px; transition: 0.2s; }
        button:hover { background: #059669; transform: translateY(-2px); }
        .btn-back { color: #9ca3af; text-decoration: none; font-size: 14px; }
        .btn-back:hover { color: white; }
    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #374151; padding-bottom:15px;">
        <h1 style="margin:0; font-size: 24px;">✏️ Edit Build: {{ $build->name }}</h1>
        <a href="{{ route('builds.index') }}" class="btn-back">Cancel</a>
    </div>

    <form action="{{ route('builds.update', $build->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="grid-main">
            <div>
                <label>Build Name</label>
                <input type="text" name="name" value="{{ $build->name }}" required>
            </div>
            <div>
                <label>Category</label>
                <select name="role_category">
                    <option value="Tank" {{ $build->role_category == 'Tank' ? 'selected' : '' }}>🛡️ Tank</option>
                    <option value="DPS" {{ $build->role_category == 'DPS' ? 'selected' : '' }}>⚔️ DPS</option>
                    <option value="Healer" {{ $build->role_category == 'Healer' ? 'selected' : '' }}>🩹 Healer</option>
                    <option value="Support" {{ $build->role_category == 'Support' ? 'selected' : '' }}>🖐️ Support</option>
                </select>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid #374151; margin-bottom: 25px;">

        <div class="grid-equip">
            <div></div> <div>
                <label>Head Slot</label>
                <select name="head_id">
                    <option value="">-- None --</option>
                    @foreach($helmets->groupBy('category') as $category => $items)
                        <optgroup label="{{ $category }}">
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" {{ $build->head_id == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Cape</label>
                <select name="cape_id">
                    <option value="">-- None --</option>
                    @foreach($capes as $item)
                        <option value="{{ $item->id }}" {{ $build->cape_id == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid-equip">
            <div>
                <label>Main Hand (Weapon)</label>
                <select name="weapon_id" required style="border-color: #fbbf24;">
                    @foreach($weapons->groupBy('category') as $category => $items)
                        <optgroup label="{{ $category }} Weapons">
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" {{ $build->weapon_id == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Armor Slot (Chest)</label>
                <select name="armor_id">
                    <option value="">-- None --</option>
                    @foreach($armors->groupBy('category') as $category => $items)
                        <optgroup label="{{ $category }}">
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" {{ $build->armor_id == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Offhand / Shield</label>
                <select name="offhand_id">
                    <option value="">-- None / 2H --</option>
                    @foreach($offhands->groupBy('category') as $category => $items)
                        <optgroup label="{{ $category }}">
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" {{ $build->offhand_id == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid-equip">
            <div>
                <label>Potion</label>
                <select name="potion_id">
                    <option value="">-- None --</option>
                    @foreach($potions as $item)
                        <option value="{{ $item->id }}" {{ $build->potion_id == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Shoe Slot</label>
                <select name="shoe_id">
                    <option value="">-- None --</option>
                    @foreach($shoes->groupBy('category') as $category => $items)
                        <optgroup label="{{ $category }}">
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" {{ $build->shoe_id == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Food</label>
                <select name="food_id">
                    <option value="">-- None --</option>
                    @foreach($foods as $item)
                        <option value="{{ $item->id }}" {{ $build->food_id == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid #374151; margin: 25px 0;">

        <div>
            <label>Build Notes</label>
            <textarea name="notes" rows="3">{{ $build->notes }}</textarea>
        </div>

        <button type="submit">💾 UPDATE BUILD</button>
    </form>
</div>

</body>
</html>
