<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Party: {{ $link->slug }}</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #1e1e24; color: #e2e2e2; padding: 20px; }
        .main-container { display: flex; gap: 30px; max-width: 1100px; margin: 0 auto; align-items: flex-start; }

        /* ROSTER (LEFT) */
        .roster-area { flex: 2; }
        .party-slot {
            display: flex; align-items: center; background: #2b2b36; margin-bottom: 8px;
            padding: 0 15px; height: 50px; border-radius: 6px; border-left: 4px solid #444;
            transition: all 0.2s; position: relative;
        }
        /* Sürükleme Hedefi Olduğunda */
        .party-slot.drag-over { border: 2px dashed #6366f1; background: #32324a; }

        .slot-number { font-weight: bold; color: #666; width: 30px; }

        /* DRAGGABLE USER CARD */
        .player-card {
            display: flex; align-items: center; flex-grow: 1; height: 100%; cursor: grab;
        }
        .player-card:active { cursor: grabbing; }
        .player-card.is-dragging { opacity: 0.5; }

        .slot-user { font-weight: bold; font-size: 15px; color: #fff; flex-grow: 1; }
        .slot-role { font-size: 12px; background: #18181d; padding: 3px 8px; border-radius: 4px; color: #aaa; text-transform: uppercase; }

        /* Role Colors */
        .role-tank { border-left-color: #3b82f6; }
        .role-healer { border-left-color: #10b981; }
        .role-dps { border-left-color: #ef4444; }
        .role-support { border-left-color: #f59e0b; }

        /* Tag (Etiket) Stilleri */
        .role-tags-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 4px;
        }

        .role-tag {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
            color: white;
            letter-spacing: 0.5px;
        }

        /* Ana Rol (Main) daha belirgin olsun */
        .tag-main { background-color: #6366f1; border: 1px solid #4f46e5; }

        /* Yan Roller (Off-spec) daha sönük olsun */
        .tag-sub { background-color: #4b5563; border: 1px solid #374151; }

        /* SIDEBAR (RIGHT) */
        .sidebar { flex: 1; background: #2b2b36; padding: 20px; border-radius: 8px; min-height: 400px; }
        .waitlist-item {
            background: #383844; padding: 10px; margin-bottom: 8px; border-radius: 4px;
            cursor: grab; display: flex; justify-content: space-between; align-items: center;
        }
        .waitlist-area { min-height: 200px; border: 2px dashed transparent; border-radius: 6px; }
        .waitlist-area.drag-over { border-color: #6366f1; background: #323242; }

        /* MODAL */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); }
        .modal-content { background: #2b2b36; margin: 10% auto; padding: 30px; border-radius: 12px; max-width: 400px; color: white; position: relative; }
        .btn-join { width: 100%; padding: 12px; background: #6366f1; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .btn-join:hover { background: #4f46e5; }
        input { width: 100%; padding: 10px; margin: 10px 0; background: #1e1e24; border: 1px solid #444; color: white; border-radius: 4px; }
    </style>
</head>
<body>

@php
    // Admin mi kontrolü? (Sadece Creator veya Admin sürükleyebilir)
    $isAdmin = auth()->check() && (
        $link->creator_id == auth()->id() ||
        in_array(auth()->user()->role, ['admin', 'content-creator'])
    );
@endphp

<div class="main-container">

    <div class="roster-area">
        <h2 style="margin-top: 0;">Party Composition</h2>

        @for ($i = 1; $i <= 20; $i++)
            @php
                $attendee = $link->attendees->where('slot_index', $i)->first();
                $roleClass = '';
                if($attendee) {
                    $role = strtolower($attendee->main_role);
                    if(str_contains($role, 'tank')) $roleClass = 'role-tank';
                    elseif(str_contains($role, 'heal')) $roleClass = 'role-healer';
                    elseif(str_contains($role, 'dps')) $roleClass = 'role-dps';
                    else $roleClass = 'role-support';
                }
            @endphp

            <div class="party-slot {{ $roleClass }}"
                 ondragover="allowDrop(event)"
                 ondragleave="leaveDrop(event)"
                 ondrop="drop(event, {{ $i }})">

                <div class="slot-number">{{ $i }}</div>

                @if($attendee)
                    <div class="player-card"
                         draggable="{{ $isAdmin ? 'true' : 'false' }}"
                         ondragstart="drag(event, {{ $attendee->id }})">
                        <div class="slot-user">{{ $attendee->user->name }}</div>
                        <div class="slot-role">{{ $attendee->main_role }}</div>
                    </div>
                @else
                    @php
                        // Template var mı? Varsa o slotun rolünü al
                        $requiredRole = $link->template_snapshot[$i]['role'] ?? 'Any';
                        $isEmpty = $requiredRole === 'Any';
                    @endphp

                    <div class="slot-user" style="color: #555; font-style: italic; display: flex; flex-direction: column; justify-content: center; height: 100%;">
                        @if(!$isEmpty)
                            <span style="color: #6366f1; font-weight: bold; font-size: 13px;">WANTED:</span>
                            <span style="color: #aaa; font-weight: bold; font-size: 16px;">{{ strtoupper($requiredRole) }}</span>
                        @else
                            Empty Slot
                        @endif
                    </div>
                @endif
            </div>
        @endfor
    </div>

    <div class="sidebar">
        <button class="btn-join" onclick="document.getElementById('joinModal').style.display='block'">Join Party</button>

        <h4 style="margin-top: 30px; border-bottom: 1px solid #444; padding-bottom: 10px;">
            Registered Members (Waitlist)
        </h4>

        <div class="waitlist-area"
             ondragover="allowDrop(event)"
             ondragleave="leaveDrop(event)"
             ondrop="drop(event, 0)">

            @foreach($link->attendees as $att)
                @if(is_null($att->slot_index))
                    <div class="waitlist-item"
                         draggable="{{ $isAdmin ? 'true' : 'false' }}"
                         ondragstart="drag(event, {{ $att->id }})">

                        <div style="font-weight: bold; color: #fff; margin-bottom: 2px;">
                            {{ $att->user->name }}
                        </div>

                        <div class="role-tags-wrapper">
                            <span class="role-tag tag-main">{{ $att->main_role }}</span>

                            @if($att->second_role) <span class="role-tag tag-sub">{{ $att->second_role }}</span> @endif
                            @if($att->third_role)  <span class="role-tag tag-sub">{{ $att->third_role }}</span> @endif
                            @if($att->fourth_role) <span class="role-tag tag-sub">{{ $att->fourth_role }}</span> @endif
                        </div>

                    </div>
                @endif
            @endforeach

            @if($link->attendees->whereNull('slot_index')->count() == 0)
                <p style="color: #666; font-size: 13px; text-align: center; margin-top: 20px;">Waitlist is empty.</p>
            @endif
        </div>
    </div>
</div>

<div id="joinModal" class="modal" onclick="if(event.target==this)this.style.display='none'">
    <div class="modal-content">
        <span style="float:right; cursor:pointer; font-size:24px;" onclick="document.getElementById('joinModal').style.display='none'">&times;</span>
        <h2>Join Party</h2>
        <form action="/go/{{ $link->slug }}/join" method="POST">
            @csrf
            <label>Main Role(Write Your Weapon)</label> <input type="text" name="main_role" required>
            <label>Second Role(Write Your Weapon)</label> <input type="text" name="second_role">
            <label>Third Role(Write Your Weapon)</label> <input type="text" name="third_role">
            <label>Fourth Role(Write Your Weapon)</label> <input type="text" name="fourth_role">
            <button type="submit" class="btn-join" style="margin-top:10px;">Sign Up</button>
        </form>
    </div>
</div>

<script>
    // 1. Sürükleme Başladığında (ID'yi kaydet)
    function drag(ev, attendeeId) {
        ev.dataTransfer.setData("attendeeId", attendeeId);
        ev.target.classList.add('is-dragging');
    }

    // 2. Üzerine Gelindiğinde (İzin ver ve efekt ekle)
    function allowDrop(ev) {
        ev.preventDefault();
        // Drop zone efektini ekle (slotun kendisine veya kapsayıcısına)
        let target = ev.target.closest('.party-slot') || ev.target.closest('.waitlist-area');
        if (target) target.classList.add('drag-over');
    }

    // 3. Üzerinden Çıkıldığında (Efekti kaldır)
    function leaveDrop(ev) {
        let target = ev.target.closest('.party-slot') || ev.target.closest('.waitlist-area');
        if (target) target.classList.remove('drag-over');
    }

    // 4. Bırakıldığında (AJAX İsteği Gönder)
    function drop(ev, slotIndex) {
        ev.preventDefault();
        var attendeeId = ev.dataTransfer.getData("attendeeId");
        var slug = "{{ $link->slug }}";

        // CSRF Token al
        var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch(`/go/${slug}/move`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify({
                attendee_id: attendeeId,
                target_slot: slotIndex
            })
        })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    location.reload(); // Sayfayı yenile ki yeni yerleşim görünsün
                } else {
                    alert('Hata: ' + (data.error || 'Bilinmeyen bir hata oluştu.'));
                }
            })
            .catch(error => console.error('Error:', error));
    }
</script>

</body>
</html>
