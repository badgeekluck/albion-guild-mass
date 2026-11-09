{{-- Shows every [data-utc] timestamp in the viewer's own timezone (data-format: datetime | time | short). --}}
<script>
(function () {
    const fmt = {
        datetime: { day: 'numeric', month: 'long', weekday: 'short', hour: '2-digit', minute: '2-digit' },
        short: { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' },
        time: { hour: '2-digit', minute: '2-digit' },
    };
    document.querySelectorAll('[data-utc]').forEach((el) => {
        el.textContent = new Date(el.dataset.utc).toLocaleString('tr-TR', fmt[el.dataset.format] || fmt.datetime);
    });
})();
</script>
