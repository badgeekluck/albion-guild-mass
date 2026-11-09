/**
 * Party screen: drag & drop, live refresh (Reverb), join form rules, build modal.
 *
 * The board (#party-board) is re-rendered by the server (GET /go/{slug}/board), so
 * all listeners are delegated to stable ancestors and survive a refresh.
 * User-provided text is only ever written with textContent.
 */

const board = document.getElementById('party-board');

if (board) {
    const slug = document.body.dataset.partySlug;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const boardUrl = board.dataset.boardUrl;
    const moveUrl = board.dataset.moveUrl;

    // ---------------------------------------------------------------- toast
    const toastEl = document.getElementById('toast');
    let toastTimer;

    function toast(message, type = 'error') {
        if (!toastEl) return;
        toastEl.textContent = message;
        toastEl.className = `toast show ${type === 'success' ? 'success' : ''}`;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toastEl.classList.remove('show'), 4000);
    }

    // ---------------------------------------------------------------- live refresh
    let dragging = null; // { id, node }
    let refreshPending = false;
    let refreshTimer = null;
    let refreshInFlight = false;

    async function refreshBoard() {
        if (dragging) {
            refreshPending = true;
            return;
        }
        if (refreshInFlight) {
            refreshPending = true;
            return;
        }

        refreshInFlight = true;
        refreshPending = false;

        try {
            const response = await fetch(boardUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                credentials: 'same-origin',
            });

            // Session expired / logged out: the server redirected to the welcome page.
            if (response.redirected || response.status === 401 || response.status === 419) {
                window.location.reload();
                return;
            }
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const html = await response.text();
            if (!dragging) {
                board.innerHTML = html;
                applyFilter();
                markPlacing();
            } else {
                refreshPending = true;
            }
        } catch (error) {
            console.error('[party] refresh failed', error);
        } finally {
            refreshInFlight = false;
            if (refreshPending && !dragging) scheduleRefresh(0);
        }
    }

    /** Debounced; a little jitter spreads the load when many viewers receive the same event. */
    function scheduleRefresh(jitter = 400) {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(refreshBoard, 100 + Math.floor(Math.random() * jitter));
    }

    // ---------------------------------------------------------------- drag & drop
    const dropZoneOf = (el) => el?.closest?.('.party-slot, .waitlist-area');

    function emptySlotPlaceholder() {
        const span = document.createElement('span');
        span.className = 'slot-empty-text';
        span.textContent = 'open';
        return span;
    }

    // ---------------------------------------------------------------- slot filters (survive live refreshes)
    let slotFilter = '';

    function applyFilter() {
        board.querySelectorAll('[data-slot-filters] button').forEach((b) => b.classList.toggle('active', b.dataset.filter === slotFilter));
        board.querySelectorAll('.party-slot').forEach((slot) => {
            const show = !slotFilter || (slotFilter === 'open' ? slot.dataset.open === '1' : slot.dataset.type === slotFilter);
            slot.classList.toggle('filtered-out', !show);
        });
    }

    board.addEventListener('click', (event) => {
        const button = event.target.closest('[data-slot-filters] button');
        if (!button) return;
        slotFilter = button.dataset.filter;
        applyFilter();
    });

    // "My build" in the You card opens the same build modal as clicking the slot.
    board.addEventListener('click', (event) => {
        const button = event.target.closest('[data-show-slot]');
        if (!button) return;
        board.querySelector(`.party-slot[data-slot="${button.dataset.showSlot}"] .slot-number`)?.click();
    });

    // Close the caller "More" menu when clicking elsewhere.
    document.addEventListener('click', (event) => {
        document.querySelectorAll('details.menu[open]').forEach((menu) => {
            if (!menu.contains(event.target)) menu.removeAttribute('open');
        });
    });

    document.querySelectorAll('[data-local-time]').forEach((el) => {
        el.textContent = new Date(el.dataset.localTime).toLocaleString(undefined, { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
    });

    board.addEventListener('dragstart', (event) => {
        const card = event.target.closest?.('[data-attendee-id]');
        if (!card || card.getAttribute('draggable') !== 'true') {
            event.preventDefault();
            return;
        }
        dragging = { id: card.dataset.attendeeId, node: card };
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', card.dataset.attendeeId);
        card.classList.add('is-dragging');
    });

    board.addEventListener('dragend', () => {
        dragging?.node.classList.remove('is-dragging');
        board.querySelectorAll('.drag-over').forEach((el) => el.classList.remove('drag-over'));
        dragging = null;
        if (refreshPending) scheduleRefresh(0);
    });

    board.addEventListener('dragover', (event) => {
        const zone = dropZoneOf(event.target);
        if (!dragging || !zone) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        zone.classList.add('drag-over');
    });

    board.addEventListener('dragleave', (event) => {
        const zone = dropZoneOf(event.target);
        if (zone && !zone.contains(event.relatedTarget)) zone.classList.remove('drag-over');
    });

    board.addEventListener('drop', (event) => {
        const zone = dropZoneOf(event.target);
        if (!dragging || !zone) return;
        event.preventDefault();
        zone.classList.remove('drag-over');
        moveTo(dragging.id, dragging.node, zone);
    });

    /** Moves a player to a slot (or to the waitlist when zone is the waitlist), optimistically. */
    async function moveTo(id, node, zone) {
        const targetSlot = zone.classList.contains('party-slot') ? Number(zone.dataset.slot) : 0;
        const sourceSlot = node.closest('.party-slot');

        if (sourceSlot && sourceSlot === zone) {
            dragging = null;
            return; // dropped on itself
        }
        if (!sourceSlot && targetSlot === 0) {
            dragging = null;
            return; // already on the waitlist
        }

        // Optimistic UI: move the existing DOM node, the server render replaces it right after.
        const waitlist = board.querySelector('.waitlist-area');
        waitlist?.querySelector('.waitlist-empty')?.remove();

        if (targetSlot > 0) {
            const body = zone.querySelector('.slot-body');
            const occupant = body.querySelector('[data-attendee-id]');
            if (occupant && occupant !== node) waitlist?.appendChild(occupant);
            body.replaceChildren(node);
            zone.classList.remove('is-empty-slot');
        } else {
            waitlist?.appendChild(node);
        }

        if (sourceSlot) {
            sourceSlot.querySelector('.slot-body').replaceChildren(emptySlotPlaceholder());
            sourceSlot.classList.add('is-empty-slot');
        }

        const headers = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        };
        const socketId = window.Echo?.socketId?.();
        if (socketId) headers['X-Socket-Id'] = socketId;

        try {
            const response = await fetch(moveUrl, {
                method: 'POST',
                headers,
                credentials: 'same-origin',
                body: JSON.stringify({ attendee_id: Number(id), target_slot: targetSlot }),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                toast(data.message || 'Taşıma başarısız oldu.');
            }
        } catch (error) {
            toast('Sunucuya ulaşılamadı, taşıma geri alındı.');
        }

        // Success: pick up the server-assigned role / warnings. Failure: roll back.
        dragging = null;
        refreshBoard();
    }

    // ---------------------------------------------------------------- tap to place (phones, tablets, or without dragging)
    // Tap a player, then tap a slot (or the waitlist). Tap the player again or press Esc to cancel.
    let placing = null; // attendee id
    const placeBar = document.createElement('div');
    placeBar.className = 'place-bar';
    placeBar.setAttribute('role', 'status');
    const placeText = document.createElement('span');
    const placeCancel = document.createElement('button');
    placeCancel.type = 'button';
    placeCancel.className = 'ui-btn ui-btn--sm';
    placeCancel.textContent = 'İptal';
    placeBar.append(placeText, placeCancel);
    document.body.appendChild(placeBar);

    const cardOf = (id) => board.querySelector(`[data-attendee-id="${CSS.escape(String(id))}"][draggable="true"]`);

    function startPlacing(card) {
        placing = card.dataset.attendeeId;
        const name = card.dataset.name || 'Oyuncu';
        placeText.textContent = `📍 ${name}: bir slota ya da yedek listesine dokun`;
        markPlacing();
    }

    function stopPlacing() {
        placing = null;
        markPlacing();
    }

    /** (Re)applies the selection marks; also called after a live refresh replaced the board. */
    function markPlacing() {
        board.querySelectorAll('.is-selected').forEach((el) => el.classList.remove('is-selected'));
        const card = placing ? cardOf(placing) : null;
        if (placing && !card) placing = null; // the player left or can't be moved anymore
        card?.classList.add('is-selected');
        board.classList.toggle('is-placing', Boolean(placing));
        placeBar.classList.toggle('show', Boolean(placing));
    }

    placeCancel.addEventListener('click', stopPlacing);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && placing) stopPlacing();
    });

    // Capture phase: while placing, a tap on a slot places the player instead of opening the build.
    board.addEventListener('click', (event) => {
        if (event.target.closest('button, a, form, input, select, textarea, label, details')) return;

        if (placing) {
            const selected = cardOf(placing);
            const zone = dropZoneOf(event.target);
            event.preventDefault();
            event.stopPropagation();
            if (!selected || event.target.closest('[data-attendee-id]') === selected) {
                stopPlacing();
                return;
            }
            if (!zone) return;
            stopPlacing();
            moveTo(selected.dataset.attendeeId, selected, zone);
            return;
        }

        const card = event.target.closest('[data-attendee-id][draggable="true"]');
        if (card) {
            event.stopPropagation();
            startPlacing(card);
        }
    }, true);

    // ---------------------------------------------------------------- modals
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.style.display = 'block';
        return modal;
    }

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-open-modal]');
        if (opener) {
            if (!openModal(opener.dataset.openModal)) window.location.reload();
            return;
        }
        if (event.target.closest('[data-close-modal]') || event.target.classList?.contains('modal')) {
            const modal = event.target.closest('.modal');
            if (modal) modal.style.display = 'none';
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') document.querySelectorAll('.modal').forEach((m) => (m.style.display = 'none'));
    });

    // ---------------------------------------------------------------- claims
    let modalSlot = null;

    function startClaim(slotNumber, roleLabel) {
        const claimModal = document.getElementById('claimModal');
        if (claimModal) {
            document.getElementById('claimSlotInput').value = slotNumber;
            document.getElementById('claimSlotNumber').textContent = `#${slotNumber}`;
            document.getElementById('claimSlotRole').textContent = roleLabel && roleLabel !== 'Any' ? roleLabel : '';
            document.querySelectorAll('.modal').forEach((m) => (m.style.display = 'none'));
            claimModal.style.display = 'block';
            claimModal.querySelector('input[name="in_game_name"]')?.focus();
            return;
        }
        const quick = document.getElementById('quickClaimForm');
        if (quick) {
            quick.querySelector('input[name="slot"]').value = slotNumber;
            quick.submit();
        }
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-claim-slot]');
        if (button) startClaim(button.dataset.claimSlot, button.dataset.claimRole);
    });

    document.getElementById('modalClaimBtn')?.addEventListener('click', () => {
        if (modalSlot) startClaim(modalSlot.dataset.slot, modalSlot.dataset.role);
    });

    const pendingClaimModal = document.getElementById('claimModal');
    if (pendingClaimModal?.dataset.openOnLoad === '1') {
        const slotNumber = document.getElementById('claimSlotInput').value;
        document.getElementById('claimSlotNumber').textContent = `#${slotNumber}`;
        pendingClaimModal.style.display = 'block';
    }

    // ---------------------------------------------------------------- build modal
    const EQUIPMENT_SLOTS = ['head', 'cape', 'weapon', 'armor', 'offhand', 'potion', 'shoe', 'food'];

    function slotLabel(text, faded = false) {
        const label = document.createElement('span');
        label.className = 'eq-label';
        label.style.cssText = `position:static; font-size:11px; text-align:center; padding:2px; line-height:1.1; color:#ccc; opacity:${faded ? 0.3 : 0.9};`;
        label.textContent = text;
        return label;
    }

    /** Icon from the local cache (/item-icons); items without a known ID or a failed load show their name. */
    function itemImage(item, onFail) {
        const img = document.createElement('img');
        img.src = item.icon_url;
        img.alt = item.name;
        img.title = item.name;
        img.loading = 'lazy';
        img.onerror = onFail;
        return img;
    }

    function setItemImage(slot, item) {
        const el = document.getElementById(`eq-${slot}`);
        if (!el) return;
        el.replaceChildren();
        el.title = item?.name || '';
        if (!item) {
            el.appendChild(slotLabel('EMPTY', true));
        } else if (item.icon_url) {
            el.appendChild(itemImage(item, () => el.replaceChildren(slotLabel(item.name))));
        } else {
            el.appendChild(slotLabel(item.name));
        }
    }

    function renderSwaps(swaps) {
        const container = document.getElementById('modalSwaps');
        container.replaceChildren();
        if (!swaps.length) return;

        const current = board.querySelector('.main-container')?.dataset.situation || '';
        const title = document.createElement('div');
        title.style.cssText = 'font-weight:bold; color:#a5b4fc; margin-bottom:6px;';
        title.textContent = '🔁 Swaps & alternatives';
        container.appendChild(title);

        // Active situation first, then other situations, then plain alternatives.
        const rank = (s) => (s.situation === current && current ? 0 : s.situation ? 1 : 2);
        [...swaps].sort((a, b) => rank(a) - rank(b)).forEach((swap) => {
            const line = document.createElement('div');
            line.className = `swap-line${swap.situation && swap.situation === current ? ' active' : ''}`;

            const situation = document.createElement('span');
            situation.className = 'swap-situation';
            situation.textContent = swap.situation_label;
            line.appendChild(situation);

            if (swap.item?.icon_url) {
                const img = itemImage(swap.item, () => img.remove());
                line.appendChild(img);
            }

            const text = document.createElement('span');
            text.textContent = `${swap.slot_label ? `${swap.slot_label}: ` : ''}${swap.item ? swap.item.name : swap.text}`;
            line.appendChild(text);
            container.appendChild(line);
        });
    }

    board.addEventListener('click', (event) => {
        if (event.target.closest('[data-attendee-id], form, button')) return;
        const slot = event.target.closest('.party-slot');
        if (!slot || slot.dataset.hasInfo !== '1') return;

        let build = null;
        try {
            build = slot.dataset.build ? JSON.parse(slot.dataset.build) : null;
        } catch {
            build = null;
        }
        const notes = [slot.dataset.note, build?.notes].filter(Boolean).join('\n');

        document.getElementById('modalBuildName').textContent = build ? build.name : 'Build Details';
        document.getElementById('modalRoleName').textContent = slot.dataset.role || '';
        const notesEl = document.getElementById('modalNotes');
        notesEl.textContent = notes || 'No special notes.';
        notesEl.style.display = notes ? 'block' : 'none';

        document.getElementById('albionGrid').style.display = build ? 'grid' : 'none';
        document.getElementById('modalNoBuild').style.display = build ? 'none' : 'block';
        if (build) EQUIPMENT_SLOTS.forEach((s) => setItemImage(s, build.items?.[s]));

        const mountRow = document.getElementById('eq-mount-row');
        mountRow.style.display = build?.items?.mount ? 'flex' : 'none';
        if (build?.items?.mount) setItemImage('mount', build.items.mount);

        document.getElementById('modalAbilities').textContent = build?.abilities ? `✨ ${build.abilities}` : '';
        renderSwaps(build?.swaps || []);

        modalSlot = slot;
        const claimBtn = document.getElementById('modalClaimBtn');
        if (claimBtn) claimBtn.style.display = slot.dataset.claimable === '1' ? 'block' : 'none';

        openModal('buildModal');
    });

    // ---------------------------------------------------------------- join form rules
    const joinForm = document.getElementById('joinPartyForm');
    if (joinForm) {
        const inputs = [...joinForm.querySelectorAll('[data-role-input]')];
        const submit = document.getElementById('joinSubmitBtn');
        const isCta = joinForm.dataset.cta === '1';
        const FILL = ['Fill', 'Fill Tank', 'Fill DPS'];
        const EXCLUSIVE = 'Bombsquad';

        const checkRules = () => {
            // "[Tank] 1h Mace" -> "1h Mace"
            inputs.forEach((input) => {
                const match = input.value.match(/^\[[^\]]*\]\s*(.+)$/);
                if (match) input.value = match[1];
            });

            const values = inputs.map((i) => i.value.trim()).filter(Boolean);
            const exclusiveIndex = inputs.findIndex((i) => i.value.trim() === EXCLUSIVE);
            const hasFill = values.some((v) => FILL.includes(v));
            const hasDuplicates = new Set(values.map((v) => v.toLowerCase())).size !== values.length;

            inputs.forEach((input, index) => {
                input.classList.toggle('exclusive-active', index === exclusiveIndex);
                const lock = exclusiveIndex !== -1 && index !== exclusiveIndex;
                if (lock) input.value = '';
                input.disabled = lock;
            });

            let valid = values.length > 0 && !hasDuplicates;
            let message = 'Sign Up';
            if (hasDuplicates) message = '⚠️ Duplicate Roles Selected';
            else if (isCta && exclusiveIndex === -1 && values.length < 2 && !hasFill) {
                valid = false;
                message = values.length === 1 ? 'Select 1 More Role' : 'Select Roles (Min 2)';
            }

            submit.disabled = !valid;
            submit.style.opacity = valid ? '1' : '0.5';
            submit.textContent = message;
        };

        inputs.forEach((input) => {
            input.addEventListener('input', checkRules);
            input.addEventListener('change', checkRules);
        });
        checkRules();

        const modal = document.getElementById('joinModal');
        if (modal?.dataset.openOnLoad === '1') modal.style.display = 'block';
    }

    // ---------------------------------------------------------------- live viewers
    const countEl = document.getElementById('live-count');
    const listEl = document.getElementById('viewer-names');
    let viewers = [];

    function renderViewers() {
        if (countEl) countEl.textContent = String(viewers.length);
        if (!listEl) return;
        listEl.replaceChildren();
        if (viewers.length === 0) {
            const li = document.createElement('li');
            li.style.cssText = 'width:100%; text-align:center; color:#666; font-style:italic;';
            li.textContent = 'No active users';
            listEl.appendChild(li);
            return;
        }
        viewers.forEach((user) => {
            const li = document.createElement('li');
            li.className = 'viewer-tag';
            const dot = document.createElement('span');
            dot.style.cssText = 'width:6px;height:6px;background:#10b981;border-radius:50%;box-shadow:0 0 5px #10b981;';
            const name = document.createElement('span');
            name.style.fontWeight = '600';
            name.textContent = user.name;
            li.append(dot, name);
            listEl.appendChild(li);
        });
    }

    const container = document.getElementById('viewer-container');
    const tooltip = document.getElementById('viewer-list-tooltip');
    if (container && tooltip) {
        let hideTimeout;
        const show = () => {
            clearTimeout(hideTimeout);
            tooltip.style.display = 'block';
        };
        const hide = () => {
            hideTimeout = setTimeout(() => (tooltip.style.display = 'none'), 300);
        };
        [container, tooltip].forEach((el) => {
            el.addEventListener('mouseenter', show);
            el.addEventListener('mouseleave', hide);
        });
    }

    if (window.Echo) {
        window.Echo.join(`party.${slug}`)
            .here((users) => {
                viewers = users;
                renderViewers();
            })
            .joining((user) => {
                if (!viewers.some((u) => u.id === user.id)) viewers.push(user);
                renderViewers();
            })
            .leaving((user) => {
                viewers = viewers.filter((u) => u.id !== user.id);
                renderViewers();
            })
            .listen('PartyUpdated', () => scheduleRefresh())
            .error((error) => console.error('[party] presence channel error', error));

        // After a reconnect we may have missed events (skip the very first connection).
        let connectedBefore = false;
        window.Echo.connector?.pusher?.connection?.bind('connected', () => {
            if (connectedBefore) scheduleRefresh(0);
            connectedBefore = true;
        });
    } else {
        renderViewers();
    }
}
