<!DOCTYPE html>
<html lang="en">
<head>
    <title>Create Build</title>
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
        button { background: #6366f1; color: white; padding: 15px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; font-size: 16px; margin-top: 20px; transition: 0.2s; }
        button:hover { background: #4f46e5; transform: translateY(-2px); }
        .btn-back { color: #9ca3af; text-decoration: none; font-size: 14px; }
        .btn-back:hover { color: white; }

        .helper-text { font-size: 11px; color: #6b7280; margin-bottom: 20px; display: block; }
    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #374151; padding-bottom:15px;">
        <h1 style="margin:0; font-size: 24px;">🛠️ Create New Build</h1>
        <a href="{{ route('builds.index') }}" class="btn-back">Cancel</a>
    </div>

    <form action="{{ route('builds.store') }}" method="POST">
        @csrf

        <div class="grid-main">
            <div>
                <label>Build Name</label>
                <input type="text" name="name" placeholder="e.g. 1H Mace ZvZ Engage" required>
                <span class="helper-text">Give it a recognizable name for the template list.</span>
            </div>
            <div>
                <label>Category</label>
                <select name="role_category">
                    <option value="Tank">🛡️ Tank</option>
                    <option value="DPS">⚔️ DPS</option>
                    <option value="Healer">🩹 Healer</option>
                    <option value="Support">🖐️ Support</option>
                </select>
                <span class="helper-text">Filters the build color in the roster.</span>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid #374151; margin-bottom: 25px;">

        <div class="grid-equip">
            <div></div> <div>
                <label>Head Slot</label>
                <select name="head_id">
                    <option value="">-- None --</option>
                    @foreach($helmets->groupBy('category') as $category => $items)
                        <optgroup label="{{ $category }}"> @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div></div> </div>
        <div class="grid-equip">
            <div></div> <div>
                <label>Head Slot</label>
                <select name="head_id">
                    <option value="">-- None --</option>
                    @foreach($helmets->groupBy('category') as $category => $items)
                        <optgroup label="{{ $category }}">
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Cape</label> <select name="cape_id">
                    <option value="">-- None --</option>
                    @foreach($capes as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid-equip">
            <div>
                <label>Main Hand</label>
                <select name="weapon_id" required style="border-color: #fbbf24;">
                    <option value="">-- Select Weapon --</option>
                    @foreach($weapons->groupBy('category') as $category => $items)
                        <optgroup label="{{ $category }} Weapons">
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Armor (Chest)</label>
                <select name="armor_id">
                    <option value="">-- None --</option>
                    @foreach($armors->groupBy('category') as $category => $items)
                        <optgroup label="{{ $category }}">
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
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
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid-equip">
            <div>
                <label>Potion</label> <select name="potion_id">
                    <option value="">-- None --</option>
                    @foreach($potions as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}</option>
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
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Food</label> <select name="food_id">
                    <option value="">-- None --</option>
                    @foreach($foods as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid #374151; margin: 25px 0;">

        <div>
            <label>Build Notes</label>
            <textarea name="notes" rows="3" placeholder="Food: Pork Omelette | Potion: Resistance | Swap: Judi Armor for engage..."></textarea>
        </div>

        <button type="submit">💾 SAVE BUILD</button>
    </form>
</div>

</body>
</html>
