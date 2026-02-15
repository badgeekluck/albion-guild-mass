<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Template: {{ $template->name }}</title>
    <style>
        body { background: #111827; color: #fff; font-family: sans-serif; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-top: 20px; }
        .slot-box { background: #1f2937; padding: 15px; border-radius: 8px; border: 1px solid #374151; }
        .slot-title { font-weight: bold; color: #6366f1; margin-bottom: 5px; display: block; }
        input { width: 90%; padding: 8px; background: #374151; border: 1px solid #4b5563; color: white; border-radius: 4px; }
        .btn-save { position: sticky; top: 20px; float: right; background: #10b981; padding: 15px 30px; font-size: 18px; color: white; border: none; border-radius: 8px; cursor: pointer; z-index: 100;}
    </style>
</head>
<body>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Edit: {{ $template->name }}</h1>
        <a href="{{ route('templates.index') }}" style="color:#bbb;">Cancel</a>
    </div>

    <form action="{{ route('templates.update', $template->id) }}" method="POST">
        @csrf @method('PUT')

        <div style="margin-bottom: 20px;">
            <label>Template Name:</label>
            <input type="text" name="name" value="{{ $template->name }}" style="font-size: 16px; width: 300px;">
            <button type="submit" class="btn-save">Save Changes</button>
        </div>

        <div class="grid">
            @for($i = 1; $i <= 20; $i++)
                @php
                    // Mevcut veriyi al veya varsayılan koy
                    $role = $template->structure[$i]['role'] ?? 'Any';
                @endphp
                <div class="slot-box">
                    <span class="slot-title">Slot {{ $i }}</span>
                    <input type="text" name="slots[{{ $i }}][role]" value="{{ $role }}" placeholder="Role Name (e.g. Main Tank)">
                </div>
            @endfor
        </div>
    </form>
</div>
</body>
</html>
